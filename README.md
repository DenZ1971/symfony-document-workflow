# SuperDemo

## About the project

SuperDemo is a demonstration Symfony 6.4 project inspired by document management workflows used in modern business applications.

The project implements a document life cycle using Symfony Workflow (State Machine), REST API and PHPUnit tests.

Main features:

* Customer management;
* Document creation;
* Document state management using Symfony Workflow;
* REST API;
* Docker-based development environment;
* Functional and unit testing with PHPUnit.

---

## Technologies

* PHP 8.2
* Symfony 6.4 (LTS)
* Doctrine ORM
* Doctrine Migrations
* MySQL 8
* Symfony Workflow (State Machine)
* PHPUnit 11
* Docker & Docker Compose
* REST API

---

## Document workflow

A document can have only one state at a time.

```
                 validate
draft ----------------------------> validated
  |                                     |
  |                                     |
  | cancel                              | send
  |                                     |
  v                                     v
cancelled                           sent
                                        |
                                        |
                                        | complete
                                        |
                                        v
                                   completed
```

Allowed transitions:

| Transition | From                   | To        |
| ---------- | ---------------------- | --------- |
| validate   | draft                  | validated |
| send       | validated              | sent      |
| complete   | sent                   | completed |
| cancel     | draft, validated, sent | cancelled |

Examples:

* draft -> validated -> sent -> completed
* draft -> cancelled
* validated -> cancelled
* sent -> cancelled

The following transition is not allowed:

```
draft -> completed
```

In this case the API returns:

```
409 Conflict
```

---

## Project structure

```
src
├── Controller
│   ├── CustomerController.php
│   └── DocumentController.php
│
├── Entity
│   ├── Customer.php
│   └── Document.php
│
├── Enum
│   └── DocumentType.php
│
├── Exception
│   └── InvalidDocumentTransitionException.php
│
└── Service
    └── DocumentService.php


tests
├── Controller
├── Service
└── Workflow
```

---

## Running the project

Start Docker containers:

```bash
docker compose up -d
```

Check running containers:

```bash
docker compose ps
```

Enter PHP container:

```bash
docker compose exec php sh
```

---

## Install dependencies

```bash
composer install
```

If required:

```bash
npm install
```

---

## Database

Create the database:

```bash
php bin/console doctrine:database:create
```

Run migrations:

```bash
php bin/console doctrine:migrations:migrate
```

Check the schema:

```bash
php bin/console doctrine:schema:validate
```

---

## Running tests

Run all tests:

```bash
php bin/phpunit
```

Run Workflow tests:

```bash
php bin/phpunit tests/Workflow
```

Run Service tests:

```bash
php bin/phpunit tests/Service
```

Run Controller tests:

```bash
php bin/phpunit tests/Controller
```

---

## API

### Create customer

```
POST /api/customers
```

Request:

```json
{
    "name":"Example s.r.o.",
    "email":"office@example.sk"
}
```

Response:

```json
{
    "id":1,
    "name":"Example s.r.o.",
    "email":"office@example.sk"
}
```

---

### Create document

```
POST /api/documents
```

Request:

```json
{
    "title":"Service contract",
    "type":"contract",
    "customerId":1
}
```

---

### Get document

```
GET /api/documents/{id}
```

Example:

```
GET /api/documents/1
```

---

### Validate document

```
PATCH /api/documents/{id}/validate
```

Example:

```
PATCH /api/documents/1/validate
```

---

### Send document

```
PATCH /api/documents/{id}/send
```

Example:

```
PATCH /api/documents/1/send
```

---

### Complete document

```
PATCH /api/documents/{id}/complete
```

Example:

```
PATCH /api/documents/1/complete
```

---

### Cancel document

```
PATCH /api/documents/{id}/cancel
```

Example:

```
PATCH /api/documents/1/cancel
```

---

## HTTP status codes

| Status | Description                 |
| ------ | --------------------------- |
| 200    | Success                     |
| 201    | Resource created            |
| 400    | Invalid JSON payload        |
| 404    | Resource was not found      |
| 409    | Invalid document transition |
| 422    | Validation error            |

---

## Test coverage

The project currently contains:

* Workflow tests;
* Service layer tests;
* Functional API tests.

Covered scenarios include:

* customer creation;
* document creation;
* document life cycle;
* allowed workflow transitions;
* forbidden transitions;
* invalid JSON payloads;
* validation errors;
* unknown customers;
* unknown documents;
* API error handling.

All tests must pass successfully before submitting changes.

```
php bin/phpunit
```

