# Mini Inventory & Order Management

A small e-commerce system for groceries (rice, dal, pulses, etc.).

- `backend/` - Laravel REST API and admin panel
- `mobile/` - Flutter customer app

## Roles

- **Customer** - registers, browses, searches, adds to cart or buys directly, places orders.
- **Admin** - manages products and updates order status.

## Features

### Customer (Flutter)
- Register / login / logout
- Category list (Rice, Dal, Pulses, ...)
- Products by category, with images
- Search products by name
- Cart (add, update quantity, remove) and a "Buy now" direct checkout
- Place order (cash on delivery)
- My orders and order status

### Admin (Laravel, Blade)
- Login (admin accounts are seeded)
- Add / edit products with image upload, price, stock, category
- Manage categories
- Order list and detail, change order status

## Order rules

- Payment: cash on delivery only.
- Stock is reduced when an order is placed. An order is rejected if stock is not enough.
- Status flow: `pending -> confirmed -> shipped -> delivered`, or `cancelled`.
- Cancelling an order puts the stock back.

## Additional scope

- Order placement runs in a DB transaction with row locking, so concurrent orders cannot oversell stock
- Form request validation and a consistent JSON response and error format
- Paginated product and order listings
- Delivery address captured at checkout, with an order status history
- Admin dashboard with total orders, pending orders and low-stock products
- Soft delete for products, so past orders keep their product details
- Prices stored on order items, so later price changes do not affect old orders
- Image validation (type and size) and storage with public URLs
- Feature tests for auth, stock handling and order status changes
- Postman collection and seeders (admin user, categories, sample products)

## Tech

- Laravel, MySQL, Sanctum (token auth), role middleware
- Blade for the admin panel
- Flutter with Provider and Dio

## Data model

- `users` (name, email, password, role)
- `categories` (name)
- `products` (category_id, name, description, price, stock, unit, image)
- `cart_items` (user_id, product_id, quantity)
- `orders` (user_id, total, status, address, phone, payment_method)
- `order_items` (order_id, product_id, price, quantity)

## API (planned)

| Method | Endpoint | Access |
|---|---|---|
| POST | `/api/register`, `/api/login` | public |
| POST | `/api/logout` | auth |
| GET | `/api/categories` | auth |
| GET | `/api/products?category_id=&search=` | auth |
| GET | `/api/products/{id}` | auth |
| GET/POST/PUT/DELETE | `/api/cart` | customer |
| POST | `/api/orders` (from cart or direct product) | customer |
| GET | `/api/orders`, `/api/orders/{id}` | customer |

Admin panel routes live under `/admin` (session auth, admin role only).

## Plan

1. Laravel setup, migrations, seeders (categories, admin user, sample products)
2. Auth API and role middleware
3. Product, category, cart and order APIs
4. Admin panel (products, orders)
5. Flutter app screens and API integration
6. Testing and setup notes

## Setup

Setup steps for the backend and the app will be added as they are built.
