# Interface Contract: REST API Endpoints for Smart Finance

**Feature**: `003-smart-finance-features`  
**Date**: 2026-10-02  
**Interface**: Backend REST API (`/api/v1/`)  
**Authentication**: Bearer Token (Sanctum via `Authorization: Bearer <token>`)  

---

## 1. Wallets API

### `GET /api/v1/wallets`
Returns all wallets owned by the authenticated user and consolidated total balance.

**Response 200 OK**:
```json
{
  "success": true,
  "data": {
    "total_balance": 2650000,
    "wallets": [
      {
        "id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
        "name": "BCA",
        "type": "BANK",
        "is_default": false,
        "balance": 2100000
      },
      {
        "id": "8c2deb4d-2b7d-4bad-8cdd-1b0d7b3dcb6e",
        "name": "DANA",
        "type": "EWALLET",
        "is_default": false,
        "balance": 350000
      },
      {
        "id": "7a3deb4d-1b7d-4bad-7bdd-0b0d7b3dcb6f",
        "name": "Cash",
        "type": "CASH",
        "is_default": true,
        "balance": 200000
      }
    ]
  }
}
```

### `POST /api/v1/wallets/transfer`
Executes an inter-wallet transfer.

**Request Payload**:
```json
{
  "from_wallet_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "to_wallet_id": "8c2deb4d-2b7d-4bad-8cdd-1b0d7b3dcb6e",
  "amount": 200000,
  "description": "Top-up DANA dari BCA"
}
```

**Response 201 Created**:
```json
{
  "success": true,
  "data": {
    "transaction_id": "3f2deb4d-0b7d-4bad-7bdd-9b0d7b3dcb6f",
    "amount": 200000,
    "from_wallet": {
      "name": "BCA",
      "new_balance": 1900000
    },
    "to_wallet": {
      "name": "DANA",
      "new_balance": 550000
    },
    "total_balance": 2650000
  }
}
```

---

## 2. Budgets API

### `GET /api/v1/budgets`
Returns active category budgets with current calendar month consumption.

**Response 200 OK**:
```json
{
  "success": true,
  "data": {
    "month": "2026-10",
    "total_budgeted": 1800000,
    "total_spent": 1250000,
    "budgets": [
      {
        "id": "1a1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
        "category_id": "2b2deb4d-2b7d-4bad-8cdd-1b0d7b3dcb6e",
        "category_name": "Makanan & Minuman",
        "category_icon": "🍔",
        "limit_amount": 1000000,
        "spent_amount": 750000,
        "remaining_amount": 250000,
        "percentage": 75.0,
        "is_exceeded": false
      }
    ]
  }
}
```

### `POST /api/v1/budgets`
Upserts a category budget.

**Request Payload**:
```json
{
  "category_id": "2b2deb4d-2b7d-4bad-8cdd-1b0d7b3dcb6e",
  "limit_amount": 1000000
}
```

---

## 3. Financial Goals API

### `GET /api/v1/goals`
Returns active savings goals with calculated progress.

**Response 200 OK**:
```json
{
  "success": true,
  "data": [
    {
      "id": "5c1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
      "name": "Modal Apotek",
      "target_amount": 100000000,
      "current_amount": 12500000,
      "remaining_amount": 87500000,
      "progress_percent": 12.5,
      "status": "ACTIVE"
    }
  ]
}
```

### `POST /api/v1/goals`
Creates a new financial goal.

**Request Payload**:
```json
{
  "name": "Modal Apotek",
  "target_amount": 100000000,
  "target_date": "2027-12-31"
}
```
