-- =====================================================
-- Three O' Clock Cafe Management System
-- Database : three_oclock_cafe
-- Part 1 : Database + Admins + Users + Categories
-- =====================================================

DROP DATABASE IF EXISTS three_oclock_cafe;

CREATE DATABASE three_oclock_cafe
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE three_oclock_cafe;

-- ===========Admin Table================
CREATE TABLE admins (

    admin_id INT AUTO_INCREMENT PRIMARY KEY,

    full_name VARCHAR(100) NOT NULL,

    email VARCHAR(120) UNIQUE NOT NULL,

    password VARCHAR(255) NOT NULL,

    phone VARCHAR(20),

    profile_image VARCHAR(255) DEFAULT 'default.png',

    role ENUM(
        'Super Admin',
        'Manager',
        'Staff'
    ) DEFAULT 'Manager',

    status ENUM(
        'Active',
        'Inactive'
    ) DEFAULT 'Active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP

);

-- ===========User's Table================
CREATE TABLE users (

    user_id INT AUTO_INCREMENT PRIMARY KEY,

    full_name VARCHAR(120) NOT NULL,

    email VARCHAR(120) UNIQUE NOT NULL,

    password VARCHAR(255) NOT NULL,

    phone VARCHAR(20) UNIQUE,

    profile_image VARCHAR(255)
    DEFAULT 'default-user.png',

    address TEXT,

    city VARCHAR(80),

    state VARCHAR(80),

    pincode VARCHAR(10),

    status ENUM(
        'Active',
        'Blocked'
    ) DEFAULT 'Active',

    email_verified BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP

);

-- ===========Categories Table================
CREATE TABLE categories (

    category_id INT AUTO_INCREMENT PRIMARY KEY,

    category_name VARCHAR(100)
    UNIQUE NOT NULL,

    category_image VARCHAR(255),

    description TEXT,

    status ENUM(
        'Active',
        'Inactive'
    ) DEFAULT 'Active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP

);

-- =====================================================
-- PART 2 : PRODUCTS
-- =====================================================

CREATE TABLE products (

    product_id INT AUTO_INCREMENT PRIMARY KEY,

    category_id INT NOT NULL,

    product_name VARCHAR(150) NOT NULL,

    slug VARCHAR(180) UNIQUE,

    description TEXT,

    short_description VARCHAR(255),

    price DECIMAL(10,2) NOT NULL,

    discount_price DECIMAL(10,2) DEFAULT NULL,

    food_type ENUM(
        'Veg',
        'Non-Veg',
        'Egg'
    ) DEFAULT 'Veg',

    spice_level ENUM(
        'Mild',
        'Medium',
        'Hot'
    ) DEFAULT 'Medium',

    preparation_time INT DEFAULT 15,

    stock INT DEFAULT 100,

    featured BOOLEAN DEFAULT FALSE,

    availability ENUM(
        'Available',
        'Unavailable'
    ) DEFAULT 'Available',

    status ENUM(
        'Active',
        'Inactive'
    ) DEFAULT 'Active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_product_category
        FOREIGN KEY (category_id)
        REFERENCES categories(category_id)
        ON DELETE CASCADE

);

CREATE INDEX idx_product_category
ON products(category_id);

CREATE INDEX idx_product_status
ON products(status);

-- =====================================================
-- PRODUCT IMAGES
-- =====================================================

CREATE TABLE product_images (

    image_id INT AUTO_INCREMENT PRIMARY KEY,

    product_id INT NOT NULL,

    image_name VARCHAR(255) NOT NULL,

    is_primary BOOLEAN DEFAULT FALSE,

    display_order INT DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_product_image
        FOREIGN KEY(product_id)
        REFERENCES products(product_id)
        ON DELETE CASCADE

);

CREATE INDEX idx_product_images
ON product_images(product_id);

-- =====================================================
-- CAFE TABLES
-- =====================================================

CREATE TABLE cafe_tables (

    table_id INT AUTO_INCREMENT PRIMARY KEY,

    table_number VARCHAR(20) UNIQUE NOT NULL,

    capacity INT NOT NULL,

    location ENUM(
        'Indoor',
        'Outdoor',
        'VIP'
    ) DEFAULT 'Indoor',

    status ENUM(
        'Available',
        'Reserved',
        'Occupied',
        'Maintenance'
    ) DEFAULT 'Available',

    description VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP

);

-- =====================================================
-- PART 3 : TABLE BOOKINGS
-- =====================================================

CREATE TABLE table_bookings (

    booking_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT DEFAULT NULL,

    table_id INT NOT NULL,

    booking_date DATE NOT NULL,

    booking_time TIME NOT NULL,

    number_of_guests INT NOT NULL,

    customer_name VARCHAR(100) NOT NULL,

    phone VARCHAR(20) NOT NULL,

    email VARCHAR(120),

    special_request TEXT,

    booking_status ENUM(
        'Pending',
        'Confirmed',
        'Cancelled',
        'Completed'
    ) DEFAULT 'Pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_booking_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL,

    CONSTRAINT fk_booking_table
        FOREIGN KEY (table_id)
        REFERENCES cafe_tables(table_id)
        ON DELETE CASCADE

);

CREATE INDEX idx_booking_date
ON table_bookings(booking_date);

CREATE INDEX idx_booking_table
ON table_bookings(table_id);

-- =====================================================
-- ORDERS
-- =====================================================

CREATE TABLE orders (

    order_id INT AUTO_INCREMENT PRIMARY KEY,

    order_number VARCHAR(20) UNIQUE NOT NULL,

    user_id INT DEFAULT NULL,

    customer_name VARCHAR(120) NOT NULL,

    phone VARCHAR(20) NOT NULL,

    email VARCHAR(120),

    address TEXT,

    order_source ENUM(
        'Website',
        'Walk-In',
        'Swiggy',
        'Zomato'
    ) DEFAULT 'Website',

    order_type ENUM(
        'Dine-In',
        'Takeaway',
        'Delivery'
    ) DEFAULT 'Delivery',

    table_id INT DEFAULT NULL,

    subtotal DECIMAL(10,2) NOT NULL,

    discount DECIMAL(10,2) DEFAULT 0,

    tax DECIMAL(10,2) DEFAULT 0,

    delivery_charge DECIMAL(10,2) DEFAULT 0,

    grand_total DECIMAL(10,2) NOT NULL,

    payment_status ENUM(
        'Pending',
        'Paid',
        'Failed',
        'Refunded'
    ) DEFAULT 'Pending',

    order_status ENUM(
        'Pending',
        'Accepted',
        'Preparing',
        'Ready',
        'Out for Delivery',
        'Completed',
        'Cancelled'
    ) DEFAULT 'Pending',

    payment_method ENUM(
        'Cash',
        'UPI',
        'Card',
        'Razorpay'
    ) DEFAULT 'Cash',

    notes TEXT,

    ordered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_order_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL,

    CONSTRAINT fk_order_table
        FOREIGN KEY (table_id)
        REFERENCES cafe_tables(table_id)
        ON DELETE SET NULL

);

CREATE INDEX idx_order_number
ON orders(order_number);

CREATE INDEX idx_order_status
ON orders(order_status);

CREATE INDEX idx_payment_status
ON orders(payment_status);

-- =====================================================
-- ORDER ITEMS
-- =====================================================

CREATE TABLE order_items (

    item_id INT AUTO_INCREMENT PRIMARY KEY,

    order_id INT NOT NULL,

    product_id INT NOT NULL,

    quantity INT NOT NULL,

    unit_price DECIMAL(10,2) NOT NULL,

    total_price DECIMAL(10,2) NOT NULL,

    special_instruction VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_item_order
        FOREIGN KEY (order_id)
        REFERENCES orders(order_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_item_product
        FOREIGN KEY (product_id)
        REFERENCES products(product_id)
        ON DELETE CASCADE

);

CREATE INDEX idx_order_items
ON order_items(order_id);

CREATE INDEX idx_order_product
ON order_items(product_id);

-- =====================================================
-- PART 4 : PAYMENTS
-- =====================================================

CREATE TABLE payments (

    payment_id INT AUTO_INCREMENT PRIMARY KEY,

    order_id INT NOT NULL,

    transaction_id VARCHAR(150) UNIQUE,

    razorpay_order_id VARCHAR(100),

    razorpay_payment_id VARCHAR(100),

    payment_method ENUM(
        'Cash',
        'UPI',
        'Card',
        'Razorpay'
    ) NOT NULL,

    payment_status ENUM(
        'Pending',
        'Success',
        'Failed',
        'Refunded'
    ) DEFAULT 'Pending',

    amount DECIMAL(10,2) NOT NULL,

    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    remarks VARCHAR(255),

    CONSTRAINT fk_payment_order
        FOREIGN KEY(order_id)
        REFERENCES orders(order_id)
        ON DELETE CASCADE

);

CREATE INDEX idx_payment_order
ON payments(order_id);

CREATE INDEX idx_transaction
ON payments(transaction_id);

-- =====================================================
-- REVIEWS
-- =====================================================

CREATE TABLE reviews (

    review_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT DEFAULT NULL,

    product_id INT NOT NULL,

    rating INT NOT NULL CHECK (
        rating BETWEEN 1 AND 5
    ),

    review TEXT,

    status ENUM(
        'Pending',
        'Approved',
        'Rejected'
    ) DEFAULT 'Pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_review_user
        FOREIGN KEY(user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL,

    CONSTRAINT fk_review_product
        FOREIGN KEY(product_id)
        REFERENCES products(product_id)
        ON DELETE CASCADE

);

CREATE INDEX idx_review_product
ON reviews(product_id);

-- =====================================================
-- CONTACT MESSAGES
-- =====================================================

CREATE TABLE contact_messages (

    message_id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    email VARCHAR(120) NOT NULL,

    phone VARCHAR(20),

    subject VARCHAR(200),

    message TEXT NOT NULL,

    status ENUM(
        'Unread',
        'Read',
        'Replied'
    ) DEFAULT 'Unread',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);

CREATE INDEX idx_contact_status
ON contact_messages(status);

-- =====================================================
-- SETTINGS
-- =====================================================

CREATE TABLE settings (

    setting_id INT AUTO_INCREMENT PRIMARY KEY,

    cafe_name VARCHAR(150) NOT NULL,

    owner_name VARCHAR(150),

    email VARCHAR(120),

    phone VARCHAR(20),

    whatsapp VARCHAR(20),

    address TEXT,

    city VARCHAR(80),

    state VARCHAR(80),

    pincode VARCHAR(10),

    gst_number VARCHAR(50),

    logo VARCHAR(255),

    favicon VARCHAR(255),

    opening_time TIME,

    closing_time TIME,

    currency VARCHAR(10) DEFAULT 'INR',

    tax_percentage DECIMAL(5,2) DEFAULT 0,

    delivery_charge DECIMAL(10,2) DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP

);

INSERT INTO settings
(
    cafe_name,
    owner_name,
    email,
    phone,
    whatsapp,
    city,
    state,
    currency,
    tax_percentage,
    delivery_charge
)
VALUES
(
    'Three O'' Clock Cafe',
    'Owner Name',
    'info@threeoclockcafe.com',
    '9876543210',
    '919876543210',
    'Ahmedabad',
    'Gujarat',
    'INR',
    5.00,
    40.00
);