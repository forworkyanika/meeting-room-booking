# หลักการทำงานของ Meeting Room Booking System

เอกสารนี้อธิบายว่าระบบทำงานอย่างไร ใช้เทคโนโลยีอะไร ทำไมเลือกแบบนั้น และปูพื้นฐานภาษา PHP / Laravel / Vue
พร้อมเทียบกับ Python (FastAPI) และ TypeScript ที่คุ้นเคยอยู่แล้ว ท้ายเอกสารมีคำถามสัมภาษณ์ที่น่าจะเจอ

---

## 1. ภาพรวมสถาปัตยกรรม

```
 Browser (Vue 3 SPA)                       Laravel (PHP)                          PostgreSQL
┌──────────────────────┐   HTTP + JSON   ┌──────────────────────────────┐   SQL   ┌──────────────┐
│ Pages / Components   │ ──────────────▶ │ routes/api.php               │ ──────▶ │ users        │
│ FullCalendar         │  Bearer token   │  └ Middleware (auth, can)    │         │ rooms        │
│ Pinia (state)        │                 │     └ FormRequest (validate) │         │ bookings     │
│ Vue Router (หน้า)     │ ◀────────────── │        └ Controller          │ ◀────── │  + EXCLUDE   │
│ axios (เรียก API)     │   JSON / 4xx    │           └ BookingService   │         │  constraint  │
└──────────────────────┘                 │              └ Eloquent Model│         └──────────────┘
                                         │           └ API Resource     │
                                         └──────────────────────────────┘
```

* **Frontend กับ Backend แยกหน้าที่กันชัดเจน**: Laravel ไม่ render หน้าเว็บ (ยกเว้น HTML เปล่าหน้าเดียว `app.blade.php`) ทุกอย่างที่ผู้ใช้เห็นมาจาก Vue
  Vue คุยกับ Laravel ผ่าน **REST API** ที่รับและส่ง JSON อย่างเดียว
* ทำแบบนี้เรียกว่า **SPA (Single Page Application)**: โหลด HTML ครั้งเดียว แล้วเปลี่ยนหน้าด้วย JavaScript โดยไม่ reload
* `routes/web.php` มีบรรทัดเดียว: ทุก URL ที่ไม่ใช่ `/api/*` จะได้ HTML เดียวกัน แล้ว Vue Router ดูว่า URL คืออะไรและควรแสดงหน้าไหน

---

## 2. ตามรอย 1 request: "จองห้อง Everest 10:00–11:00"

1. **ผู้ใช้ลากช่วงเวลาบนปฏิทิน** → FullCalendar เรียก `select(info)` → เปิด `BookingFormModal` พร้อมเวลาที่ลาก
2. **ระหว่างกรอกฟอร์ม** `watch()` คอยดูห้อง/เวลา เมื่อเปลี่ยนจะรอ 300ms (debounce) แล้วยิง
   `GET /api/availability?room_id=1&start_time=...&end_time=...`
   ถ้าเวลาชน จะขึ้นกล่องสีเหลือง *"This time clashes with…"* และปุ่ม Book จะกดไม่ได้
3. **กด Book room** → axios ยิง `POST /api/bookings` (interceptor แนบ `Authorization: Bearer <token>` ให้อัตโนมัติ)
   เวลาถูกแปลงเป็น ISO UTC เช่น `2026-11-30T03:00:00.000Z` เพื่อให้ server รู้เวลาที่แน่นอน
4. **Laravel รับ request** ผ่านชั้นต่าง ๆ ตามลำดับ:
   * `routes/api.php` จับคู่ URL → `BookingController@store`
   * middleware `auth:sanctum` ตรวจ token แล้วหา user (ถ้าไม่มีหรือไม่ถูกต้องจะได้ **401**)
   * `StoreBookingRequest` ตรวจข้อมูล: ห้องมีจริงและเปิดใช้, เวลาอยู่ในอนาคต, จบหลังเริ่ม, ยาว 15 นาทีถึง 8 ชม., จำนวนคนไม่เกินความจุ (ถ้าผิดได้ **422** พร้อม error รายช่อง)
   * Controller เรียก `BookingService::create()`
5. **BookingService** (หัวใจของระบบ)
   * แปลงเวลาเป็น timezone ของแอป (`Asia/Bangkok`) ด้วย `AppTime::parse()`
   * เปิด **transaction** แล้ว `SELECT ... FOR UPDATE` แถวของห้อง (lock) เพื่อให้ request ที่จองห้องเดียวกันพร้อมกันต้องรอคิว
   * หา booking ที่ชน ถ้าเจอจะ throw `BookingConflictException` → **409**
   * ถ้าไม่ชนก็บันทึก โดยตั้ง `status = pending` (ถ้าคนจองเป็น admin จะได้ `approved` ทันที)
6. **ส่งกลับ** ผ่าน `BookingResource` (กำหนดรูป JSON) → **201 Created**
7. Vue แสดง toast *"Booking sent for approval"* แล้วสั่ง `refetchEvents()` ให้ปฏิทินโหลดใหม่ booking จะโผล่เป็นกรอบเส้นประ (pending)
8. **Admin** เปิดหน้า Approvals → กด Approve → `PATCH /api/admin/bookings/{id}/approve`
   middleware `can:admin` กันไม่ให้ user ธรรมดาเข้า (**403**) → `BookingService::approve()` ตรวจชนซ้ำอีกรอบแล้วเปลี่ยนสถานะ

---

## 3. Booking Conflict Detection (ส่วนที่ควรอธิบายได้ตอนสัมภาษณ์)

### สูตรเช็คช่วงเวลาทับกัน

ช่วง `[a_start, a_end)` และ `[b_start, b_end)` **ทับกันก็ต่อเมื่อ**

```
a_start < b_end  AND  b_start < a_end
```

ใน code อยู่ที่ `Booking::scopeOverlapping()`:

```php
$query->where('start_time', '<', $end)->where('end_time', '>', $start);
```

| ของเดิม 10:00–11:00 เทียบกับ | ผล |
|---|---|
| 10:30–11:30 (เริ่มระหว่างทาง) | ชน |
| 09:30–10:30 (จบระหว่างทาง) | ชน |
| 10:15–10:45 (อยู่ข้างใน) | ชน |
| 09:00–12:00 (ครอบทั้งหมด) | ชน |
| **11:00–12:00 (ต่อท้ายพอดี)** | **ไม่ชน** เพราะใช้ `<` ไม่ใช่ `<=` (ช่วงแบบ half-open `[)`) |

สูตรเดียวครอบคลุมทุกกรณี ไม่ต้องเขียน `if` แยก 4 แบบ
booking ที่ **rejected / cancelled ไม่นับ** (scope `active()` เอาเฉพาะ `pending` และ `approved`)

### ทำไมต้องกัน 3 ชั้น

| ชั้น | อยู่ที่ไหน | กันอะไร |
|---|---|---|
| 1. UI | `BookingFormModal` + `/api/availability` | UX ที่ดี: บอกผู้ใช้ก่อนกด (แต่ข้ามได้ เช่นยิง API ตรง) |
| 2. Application | `BookingService` + `lockForUpdate()` | กฎธุรกิจจริง + ส่ง 409 พร้อมรายการที่ชน |
| 3. Database | `EXCLUDE USING gist` ใน migration | **Race condition**: 2 request มาพร้อมกันเป๊ะ |

**Race condition คืออะไร**: A กับ B กดจองห้องเดียวกันเวลาเดียวกันพร้อมกัน ทั้งคู่ SELECT แล้วเห็นว่า "ว่าง" ทั้งคู่ INSERT ได้ จึงจองซ้ำ
* `lockForUpdate()` ทำให้คนที่สองต้องรอจนคนแรก commit แล้วค่อย SELECT จึงเห็นข้อมูลล่าสุด
* `EXCLUDE` constraint เป็นด่านสุดท้าย ต่อให้มีคนเขียน code ใหม่ที่ลืมเช็ค database ก็จะ reject เอง (SQLSTATE `23P01`) และ service แปลงเป็น 409 แบบเดียวกัน

```sql
ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
EXCLUDE USING gist (room_id WITH =, tsrange(start_time, end_time, '[)') WITH &&)
WHERE (status IN ('pending', 'approved'));
```

อ่านว่า: "ห้ามมี 2 แถวที่ `room_id` **เท่ากัน** และช่วงเวลา **ทับกัน** (`&&`) โดยดูเฉพาะแถวที่ยัง active"
ต้องเปิด extension `btree_gist` ก่อน เพื่อให้ index แบบ GiST ใช้ `=` กับตัวเลขได้ ข้อนี้เป็นจุดเด่นของ PostgreSQL ที่ MySQL ไม่มี

---

## 4. Admin Approval Workflow (State machine)

```
           ┌──approve──▶ approved ──cancel──┐
 pending ──┤                                 ├──▶ cancelled
           ├──reject───▶ rejected            │
           └──cancel─────────────────────────┘
```

* เปลี่ยนสถานะได้ **ที่เดียวคือ `BookingService`** ถ้าเปลี่ยนผิดลำดับ (เช่น approve ของที่ cancelled ไปแล้ว) จะได้ `InvalidBookingTransitionException` → **422**
* Reject **ต้องใส่เหตุผล** (`ReviewBookingRequest` บังคับ `note` เฉพาะ route reject) ผู้จองจะเห็นเหตุผลในหน้า My bookings
* Approve จะ **เช็คชนซ้ำอีกรอบ** เป็นการป้องกันเผื่อไว้ (defensive)
* เก็บว่า **ใครอนุมัติ เมื่อไร** (`reviewed_by`, `reviewed_at`) ไว้เป็น audit trail
* สิทธิ์ยกเลิกอยู่ใน `BookingPolicy::cancel()`: ต้องเป็นเจ้าของหรือ admin และ booking ยังไม่จบ

---

## 5. Authentication: Laravel Sanctum (token)

1. `POST /api/login` → ตรวจ email/password (`Hash::check` กับ bcrypt) → สร้าง token เก็บใน `personal_access_tokens` (เก็บแบบ hash)
2. Vue เก็บ token ใน `localStorage` และ axios interceptor แนบ `Authorization: Bearer ...` ไปกับทุก request
3. ได้ 401 เมื่อไร interceptor จะลบ token แล้วพาไปหน้า login
4. Logout ลบ token นั้นออกจาก DB (revoke)

> **Trade-off ที่ควรรู้**: Sanctum มีอีกโหมดคือ *SPA cookie auth* (session + CSRF) ที่ปลอดภัยกว่าเรื่อง XSS เพราะ JavaScript อ่าน cookie ไม่ได้
> โปรเจกต์นี้เลือก token เพราะเรียบง่ายและเอา API ไปใช้กับ mobile/Postman ได้ทันที ถ้าถูกถามให้ตอบว่า production ที่ frontend อยู่ domain เดียวกันควรใช้ cookie mode

---

## 6. Tech stack: แต่ละตัวทำอะไร ทำไมเลือก

| เทคโนโลยี | หน้าที่ | เทียบกับที่คุ้นเคย |
|---|---|---|
| **PHP 8** | ภาษาฝั่ง server | Python |
| **Laravel 13** | Web framework: routing, ORM, validation, auth, migration, test | FastAPI + SQLAlchemy + Alembic + Pydantic รวมกันในตัวเดียว |
| **Composer** | package manager ของ PHP (`composer.json`) | pip / uv (`pyproject.toml`) |
| **Artisan** | CLI ของ Laravel (`php artisan migrate`, `make:model`, `test`) | `alembic`, `manage.py` |
| **Eloquent** | ORM แบบ Active Record (model = แถวใน table) | SQLAlchemy ORM |
| **Sanctum** | API token auth | FastAPI + OAuth2 bearer |
| **PostgreSQL** | Database (+ EXCLUDE constraint, jsonb) | ตัวเดียวกับงาน DFT |
| **PHPUnit** | Test framework | pytest |
| **Vue 3** | UI framework แบบ component + reactivity | React |
| **Vue Router** | เปลี่ยนหน้าใน SPA + route guard | React Router |
| **Pinia** | global state (user ที่ login, toast) | Zustand / Redux |
| **axios** | HTTP client + interceptors | fetch / axios |
| **Bootstrap 5** | CSS framework: grid, responsive, modal, form | Tailwind / MUI |
| **FullCalendar** | ปฏิทินแบบ interactive (month/week/day/list, ลากเลือกเวลา) | — |
| **Vite** | build tool / dev server ของ frontend | ตัวเดียวกับงาน DFT |
| **Git / GitHub** | version control | — |

---

## 7. ภาษา PHP สำหรับคนที่เขียน Python / TypeScript เป็นแล้ว

### 7.1 Syntax พื้นฐาน

```php
<?php                                    // ทุกไฟล์ PHP ขึ้นต้นด้วยแท็กนี้

$name = 'Everest';                       // ตัวแปรขึ้นต้นด้วย $ เสมอ
$capacity = 20;
echo "Room {$name} holds {$capacity}";   // "..." แทรกตัวแปรได้ ส่วน '...' ไม่แทรก
$full = 'Room ' . $name;                 // ต่อ string ใช้จุด . (ไม่ใช่ +)

// array ตัวเดียวใช้ได้ทั้ง list และ dict
$amenities = ['TV', 'Whiteboard'];                  // Python: list
$room = ['name' => 'Fuji', 'capacity' => 8];        // Python: dict
$room['name'];                                      // 'Fuji'
count($amenities);                                  // len()

foreach ($room as $key => $value) { ... }           // for key, value in room.items()

function add(int $a, int $b = 1): int { return $a + $b; }   // type hint คล้าย Python
```

### 7.2 OOP: class, `->` และ `::`

```php
class Booking extends Model                // extends = สืบทอด
{
    public function isCancellable(): bool
    {
        return $this->status->isActive()   // $this = self, -> = เรียก method/property ของ object
            && $this->end_time->isFuture();
    }
}

$booking->title;                // property ของ object (Python: booking.title)
Booking::query();               // :: = static method / ค่าคงที่ของ class (Python: Booking.query())
BookingStatus::Pending;         // enum case
```

### 7.3 Namespace และ `use` (เหมือน import)

```php
namespace App\Services;                  // ไฟล์นี้อยู่ใน package App\Services
use App\Models\Booking;                  // from app.models import Booking
```

Composer **autoload** (PSR-4) จับคู่ `App\Services\BookingService` กับไฟล์ `app/Services/BookingService.php` ให้อัตโนมัติ จึงไม่ต้อง `require` เอง

### 7.4 Feature PHP สมัยใหม่ (8.x) ที่ใช้ในโปรเจกต์นี้

| Syntax | ความหมาย | ตัวอย่างในโปรเจกต์ |
|---|---|---|
| `fn ($q) => $q->active()` | arrow function (lambda) | `->when(..., fn ($q) => ...)` |
| `$a?->b` | null-safe (TS: `a?.b`) | `$this->reviewed_at?->toIso8601String()` |
| `$a ?? 'x'` | ถ้า null ใช้ค่า default | `$this->amenities ?? []` |
| `enum X: string { case A = 'a'; }` | backed enum | `app/Enums/BookingStatus.php` |
| `#[Fillable([...])]` | attribute (Python: decorator) | `app/Models/Booking.php` |
| `private readonly BookingService $bookings` ใน constructor | constructor property promotion (คล้าย dataclass) | `BookingController` |
| `[...$data, 'user_id' => 1]` | spread (Python: `{**data}`) | `BookingService::create` |
| `[$a, $b] = $row` | destructuring | `DatabaseSeeder` |
| `match ($x) { ... }` | Python `match` / switch แบบคืนค่า | — |

### 7.5 การรัน PHP บนเว็บต่างจาก Python server อย่างไร

PHP แบบดั้งเดิมจะ **เริ่มใหม่ทุก request**: boot แอป → ทำงาน → ทิ้ง state ทั้งหมด (shared-nothing)
ข้อดีคือ memory ไม่รั่วข้าม request และไม่ต้องกังวลเรื่อง global state ข้อเสียคือ boot ซ้ำทุกครั้ง (มี OPcache ช่วย cache bytecode)
ต่างจาก FastAPI/uvicorn ที่ process อยู่ตลอด (ถ้าต้องการแบบนั้นใน Laravel มี **Octane**)

---

## 8. แนวคิดของ Laravel ที่ใช้ในโปรเจกต์ (แต่ละไฟล์ทำอะไร)

| แนวคิด | ไฟล์ | อธิบาย |
|---|---|---|
| **Migration** | `database/migrations/*` | เขียน schema เป็น code มีประวัติ version (Alembic) |
| **Model + Eloquent** | `app/Models/*` | class = table, object = แถว, `casts()` แปลงชนิด เช่น `status` เป็น enum, `start_time` เป็น Carbon datetime |
| **Relationship** | `belongsTo`, `hasMany` | `$booking->room->name` จะ query ห้องให้ (ใช้ `->with('room')` เพื่อเลี่ยงปัญหา N+1) |
| **Query scope** | `scopeActive`, `scopeOverlapping` | เงื่อนไข query ที่ใช้ซ้ำ เรียกแบบ `Booking::active()->overlapping($s, $e)` |
| **Factory / Seeder** | `database/factories`, `seeders` | สร้างข้อมูลปลอมไว้เทสและเดโม |
| **Route** | `routes/api.php` | URL → controller + middleware |
| **Middleware** | `auth:sanctum`, `can:admin`, `throttle:10,1` | ด่านก่อนถึง controller: ต้อง login, ต้องเป็น admin, จำกัด 10 ครั้งต่อนาที (กัน brute force) |
| **FormRequest** | `app/Http/Requests/*` | กฎ validate (Pydantic) ถ้าไม่ผ่านจะตอบ 422 ให้อัตโนมัติ |
| **Controller** | `app/Http/Controllers/Api/*` | บาง ๆ: รับ request → เรียก service → คืน resource |
| **Service** | `app/Services/BookingService.php` | business logic อยู่ที่เดียว ทดสอบง่าย controller ไม่อ้วน |
| **API Resource** | `app/Http/Resources/*` | กำหนดรูป JSON ที่ส่งออก (Pydantic response model) ซ่อน field ที่ไม่ควรส่ง |
| **Policy / Gate** | `BookingPolicy`, `Gate::define('admin')` | authorization: *ใครทำอะไรกับ object ไหนได้* |
| **Service Container (DI)** | constructor ของ controller | Laravel สร้าง `BookingService` แล้ว inject ให้เอง (FastAPI `Depends`) |
| **Custom Exception + `render()`** | `app/Exceptions/*` | throw ที่ไหนก็ได้ แล้วกลายเป็น HTTP response ที่ถูกต้อง (409/422) |
| **Feature test** | `tests/Feature/*` | ยิง HTTP เข้าแอปจริง + DB จริง (`RefreshDatabase` ล้าง DB ให้ทุกเทส) |

---

## 9. ฝั่ง Vue 3 (Composition API)

| แนวคิด | ตัวอย่างในโปรเจกต์ |
|---|---|
| **SFC** (`.vue` = `<script setup>` + `<template>`) | ทุกไฟล์ใน `resources/js/pages` และ `components` |
| `ref()` / `reactive()` | state ที่เปลี่ยนแล้ว UI อัปเดตเอง (React `useState`) |
| `computed()` | ค่าที่คำนวณจาก state (`selectedRoom`) |
| `watch()` | ทำอะไรเมื่อ state เปลี่ยน: เช็ค availability เมื่อเวลาเปลี่ยน |
| `props` / `emit` | ส่งข้อมูลลง และส่ง event ขึ้น (`@created="refetch"`) |
| `defineExpose` + template `ref` | ให้ parent เรียก `formModal.value.open()` |
| **Composable** | `useBookingActions()` = logic ที่ใช้ซ้ำ (React custom hook) |
| **Pinia store** | `stores/auth.js` เก็บ user ที่ login, `isAdmin` |
| **Router guard** | `router.beforeEach` ถ้ายังไม่ login ส่งไป `/login`, ถ้าไม่ใช่ admin ห้ามเข้า `/admin/*` |
| **Lazy-loaded routes** | `component: () => import(...)` โหลด code ของหน้านั้นเมื่อเปิดจริง ทำให้ bundle แรกเล็กลง |

> **ข้อควรจำ**: การซ่อนเมนู admin ใน Vue เป็นแค่ **UX** ความปลอดภัยจริงอยู่ที่ backend (`can:admin`, Policy) เสมอ

**Responsive (Bootstrap)**: ใช้ grid `col-sm-6 col-lg-4`, navbar ที่ยุบเป็นปุ่ม hamburger, modal เต็มจอบนมือถือ (`modal-fullscreen-sm-down`)
และปฏิทินเปิดเป็น list view เมื่อจอแคบกว่า 768px

---

## 10. คำถามสัมภาษณ์ที่น่าจะเจอ + แนวตอบ

1. **ป้องกันจองซ้ำยังไง?**
   สูตร overlap `start < other_end AND end > other_start` เช็คใน transaction ที่ lock แถวห้อง และมี PostgreSQL EXCLUDE constraint เป็นด่านสุดท้ายกัน race condition
2. **ถ้า 2 คนกดพร้อมกัน?**
   `SELECT ... FOR UPDATE` ทำให้ต่อคิวกัน ถ้ายังหลุดมาได้ DB constraint จะ reject (23P01) แล้วแปลงเป็น 409
3. **ทำไมแยก Service ออกจาก Controller?**
   business rule อยู่ที่เดียว ใช้ซ้ำได้ (create, approve ใช้ `findConflicts` ตัวเดียวกัน) ทดสอบง่าย controller เหลือแค่เรื่อง HTTP
4. **Authentication กับ Authorization ต่างกันยังไง?**
   Authentication = เป็นใคร (Sanctum token) ส่วน Authorization = ทำอะไรได้ (Gate `admin`, `BookingPolicy`)
5. **REST status code ที่ใช้?**
   200, 201 (สร้างสำเร็จ), 204 (ลบ/logout), 401 (ไม่ได้ login), 403 (ไม่มีสิทธิ์), 404, 409 (ชน), 422 (validate ไม่ผ่าน), 429 (ยิงถี่เกินไป)
6. **N+1 query คืออะไร?**
   loop 15 bookings แล้วเรียก `$b->room` จะเกิด 15 query เพิ่ม แก้ด้วย eager load `->with('room', 'user')` ให้ query ทีละ relation รวดเดียว
7. **Timezone จัดการยังไง?**
   browser ส่ง ISO UTC มา, backend แปลงเป็น `Asia/Bangkok` ก่อนบันทึก (column เป็น timestamp ไม่มี timezone จึงต้องตกลง zone เดียว), ตอบกลับเป็น ISO มี offset `+07:00`
8. **ทำไมห้องที่มีประวัติลบไม่ได้?**
   foreign key `restrictOnDelete` + API ตอบ 409 ให้ใช้ deactivate แทน เพื่อไม่ให้ประวัติการจองหาย

---

## 11. คำสั่งที่ใช้บ่อย

```bash
php artisan serve                 # รัน backend ที่ :8000
npm run dev                       # Vite dev server (hot reload) รันคู่กับด้านบน
npm run build                     # build frontend สำหรับ production

php artisan migrate               # รัน migration ใหม่
php artisan migrate:fresh --seed  # ลบทุก table แล้วสร้างใหม่ + ข้อมูลเดโม
php artisan route:list --path=api # ดู route ทั้งหมด
php artisan tinker                # REPL เล่นกับ model ได้ เช่น Booking::count()
php artisan test                  # รันเทส
vendor/bin/pint                   # จัด format code PHP (เหมือน ruff format / prettier)

php artisan make:model Foo -mf    # สร้าง model + migration + factory
php artisan make:controller Api/FooController
```
