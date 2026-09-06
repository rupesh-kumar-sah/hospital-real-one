# MediCare HMS — Decoupled Architecture Guide & API Reference

## 1. Architecture Summary

```
  ┌────────────────────────────────────────────────────────┐
  │                 NEXT.JS FRONTEND (Vercel)              │
  │  - Next.js 14 App Router (TypeScript / React)          │
  │  - Axios / Fetch Client with Silent Token Refresh      │
  │  - Role-based UI guards (UX only, API enforces RBAC)   │
  └───────────────────────────┬────────────────────────────┘
                              │ HTTPS (JSON Payloads)
                              │ Authorization: Bearer <JWT>
                              │ Cookie: hms_refresh_token
                              ▼
  ┌────────────────────────────────────────────────────────┐
  │               PHP REST API BACKEND (Render)            │
  │  - Base Route: /api/v1/                                │
  │  - Stateless HMAC-SHA256 JWT Verification              │
  │  - Database-backed Revocable Refresh Tokens (7-day)    │
  │  - AES-256-GCM Field-Level Medical Encryption (E2EE)   │
  │  - Unified CORS & Strict CSP Security Headers          │
  └───────────────────────────┬────────────────────────────┘
                              │ Private Network / SSL
                              ▼
  ┌────────────────────────────────────────────────────────┐
  │               MYSQL 8.0 DATABASE (Render)              │
  │  - Private network connectivity                        │
  │  - Composite performance indexes                       │
  │  - refresh_tokens tracking & blacklist table           │
  └────────────────────────────────────────────────────────┘
```

---

## 2. API Endpoints Map (`/api/v1/`)

### Authentication (`/api/v1/auth/`)
| Method | Endpoint | Description | Auth Required |
|---|---|---|---|
| `POST` | `/api/v1/auth/login.php` | Authenticate user, issue 15-min JWT + HttpOnly refresh cookie | Public |
| `POST` | `/api/v1/auth/refresh.php` | Rotate refresh token, issue new 15-min JWT | Refresh Cookie |
| `POST` | `/api/v1/auth/logout.php` | Revoke refresh token in database, clear cookie | Public |
| `GET` | `/api/v1/auth/me.php` | Return authenticated user identity & linked doctor/patient ID | Any Authenticated |
| `POST` | `/api/v1/auth/register.php` | Patient self-registration with UHID generation | Public |
| `POST` | `/api/v1/auth/forgot-password.php` | Request password reset token | Public |
| `POST` | `/api/v1/auth/reset-password.php` | Reset password using verified token | Public |

### Patient Portal (`/api/v1/patient/`)
| Method | Endpoint | Description | Role Required |
|---|---|---|---|
| `GET` | `/api/v1/patient/dashboard.php` | Patient summary (visits, RX, lab, balance) | `patient` |
| `GET` | `/api/v1/patient/appointments.php` | List patient appointments | `patient` |
| `POST` | `/api/v1/patient/appointments.php` | Book new OPD appointment | `patient` |
| `GET` | `/api/v1/patient/medical-records.php` | View decrypted EMR clinical consultations | `patient` |
| `GET` | `/api/v1/patient/prescriptions.php` | View electronic prescriptions & medications | `patient` |
| `GET` | `/api/v1/patient/lab-reports.php` | View laboratory diagnostic reports | `patient` |
| `GET` | `/api/v1/patient/bills.php` | View invoices, itemized breakdown & payment QR | `patient` |
| `GET/PUT` | `/api/v1/patient/profile.php` | View and update patient demographics | `patient` |

### Receptionist & OPD Desk (`/api/v1/receptionist/`)
| Method | Endpoint | Description | Role Required |
|---|---|---|---|
| `GET` | `/api/v1/receptionist/dashboard.php` | Live OPD counts, today's schedule, revenue | `receptionist`, `admin` |
| `POST` | `/api/v1/receptionist/register-patient.php` | In-person intake with auto UHID generator | `receptionist`, `admin` |
| `GET` | `/api/v1/receptionist/search-patient.php` | Fast search by UHID, phone, or name | `receptionist`, `doctor`, `nurse`, `admin` |
| `GET/POST` | `/api/v1/receptionist/appointments.php` | Appointment schedule, approve & cancel | `receptionist`, `admin` |
| `POST` | `/api/v1/receptionist/check-in.php` | Check in patient, token sequence & OPD bill | `receptionist`, `admin` |
| `POST` | `/api/v1/receptionist/billing.php` | Create Point-of-Sale invoice with line items | `receptionist`, `admin` |
| `GET/PATCH` | `/api/v1/receptionist/invoices.php` | View invoice details & update payment status | `receptionist`, `admin`, `patient` |

### Doctor Module (`/api/v1/doctor/`)
| Method | Endpoint | Description | Role Required |
|---|---|---|---|
| `GET` | `/api/v1/doctor/dashboard.php` | Doctor waiting queue, completed metrics | `doctor` |
| `POST` | `/api/v1/doctor/consultation.php` | Record diagnosis, vitals, prescriptions, lab | `doctor` |
| `GET` | `/api/v1/doctor/patient-history.php` | Decrypted historical clinical records | `doctor`, `nurse`, `admin` |
| `POST` | `/api/v1/doctor/admit.php` | Inpatient admission & bed allocation | `doctor`, `admin` |
| `POST` | `/api/v1/doctor/discharge.php` | Inpatient discharge & summary | `doctor`, `admin` |

### Pharmacy Module (`/api/v1/pharmacy/`)
| Method | Endpoint | Description | Role Required |
|---|---|---|---|
| `GET` | `/api/v1/pharmacy/dashboard.php` | Prescription queue & stock statistics | `pharmacist`, `admin` |
| `POST` | `/api/v1/pharmacy/dispense.php` | Fulfill prescription, deduct inventory | `pharmacist`, `admin` |
| `GET/POST` | `/api/v1/pharmacy/inventory.php` | Inventory list & add new medicines | `pharmacist`, `admin` |
| `GET` | `/api/v1/pharmacy/stock-alerts.php` | Low-stock and near-expiry medication list | `pharmacist`, `admin` |

### Laboratory Module (`/api/v1/lab/`)
| Method | Endpoint | Description | Role Required |
|---|---|---|---|
| `GET` | `/api/v1/lab/dashboard.php` | Test order queues & diagnostic metrics | `lab_technician`, `admin` |
| `POST` | `/api/v1/lab/collect-sample.php` | Acknowledge specimen collection | `lab_technician`, `admin` |
| `POST` | `/api/v1/lab/upload-result.php` | Enter test readings, abnormal flags | `lab_technician`, `admin` |
| `GET/POST` | `/api/v1/lab/catalog.php` | Master test catalog & investigation rates | Public (GET) / Tech (POST) |

### Nursing Module (`/api/v1/nurse/`)
| Method | Endpoint | Description | Role Required |
|---|---|---|---|
| `GET` | `/api/v1/nurse/dashboard.php` | Inpatient roster & occupied bed counts | `nurse`, `admin` |
| `POST` | `/api/v1/nurse/vitals.php` | Log patient vital signs (BP, Pulse, Temp) | `nurse`, `doctor`, `admin` |
| `POST` | `/api/v1/nurse/medication.php` | Log Medication Administration Record (MAR) | `nurse`, `doctor`, `admin` |
| `POST` | `/api/v1/nurse/nursing-notes.php` | Log shift clinical observations | `nurse`, `admin` |

### Admin & Metadata (`/api/v1/admin/`, `/api/v1/`)
| Method | Endpoint | Description | Role Required |
|---|---|---|---|
| `GET` | `/api/v1/admin/dashboard.php` | Hospital analytics, revenue, audit feed | `admin` |
| `GET/POST` | `/api/v1/admin/users.php` | Staff user accounts management | `admin` |
| `GET/POST` | `/api/v1/admin/pricing.php` | Service pricing catalog | `admin`, `receptionist` |
| `GET/POST` | `/api/v1/admin/payment-methods.php` | Manage payment QR codes (Base64) | `admin`, `receptionist` |
| `GET` | `/api/v1/admin/audit-logs.php` | Query system audit trails | `admin` |
| `GET` | `/api/v1/departments.php` | Public/authenticated clinical depts list | Public |
| `GET` | `/api/v1/doctors.php` | Public/authenticated doctors catalog | Public |

---

## 3. Next.js Frontend Silent Refresh Pattern (Axios Example)

```typescript
import axios from 'axios';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'https://medicare-hms.onrender.com/api/v1';

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true, // Sends HttpOnly refresh token cookie
  headers: {
    'Content-Type': 'application/json',
  },
});

let accessToken: string | null = null;

export const setAccessToken = (token: string | null) => {
  accessToken = token;
};

// Request Interceptor: Attach Bearer Token
apiClient.interceptors.request.use((config) => {
  if (accessToken && config.headers) {
    config.headers.Authorization = `Bearer ${accessToken}`;
  }
  return config;
});

// Response Interceptor: Silent Token Refresh on 401
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;
      try {
        const refreshResponse = await axios.post(
          `${API_BASE_URL}/auth/refresh.php`,
          {},
          { withCredentials: true }
        );
        const newAccessToken = refreshResponse.data.data.access_token;
        setAccessToken(newAccessToken);
        originalRequest.headers.Authorization = `Bearer ${newAccessToken}`;
        return apiClient(originalRequest);
      } catch (refreshErr) {
        setAccessToken(null);
        if (typeof window !== 'undefined') {
          window.location.href = '/login';
        }
        return Promise.reject(refreshErr);
      }
    }
    return Promise.reject(error);
  }
);
```

---

## 4. Module Cutover & Coexistence Checklist

| Module | API Endpoint Status | Legacy PHP View Status | Coexistence Verified | Cutover Readiness |
|---|---|---|---|---|
| **Auth & Sessions** | `/api/v1/auth/*` | `/auth/login.php`, `register.php` | Yes (Shared DB) | 100% |
| **Patient Portal** | `/api/v1/patient/*` | `/patient/*.php` | Yes (Shared DB) | 100% |
| **Receptionist Desk**| `/api/v1/receptionist/*`| `/receptionist/*.php` | Yes (Shared DB) | 100% |
| **Doctor OPD & EMR** | `/api/v1/doctor/*` | `/doctor/*.php` | Yes (Shared DB) | 100% |
| **Pharmacy & Stock** | `/api/v1/pharmacy/*` | `/pharmacy/*.php` | Yes (Shared DB) | 100% |
| **Laboratory** | `/api/v1/lab/*` | `/lab/*.php` | Yes (Shared DB) | 100% |
| **Inpatient Nursing**| `/api/v1/nurse/*` | `/nurse/*.php` | Yes (Shared DB) | 100% |
| **Admin & Pricing** | `/api/v1/admin/*` | `/admin/*.php` | Yes (Shared DB) | 100% |
