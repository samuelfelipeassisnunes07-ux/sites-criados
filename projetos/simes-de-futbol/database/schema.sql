-- =====================================================================
--  Simês de Fútbol - Estrutura do banco de dados (MySQL / MariaDB)
--  ATENÇÃO: este script RECRIA as tabelas (DROP + CREATE). Use apenas em
--  instalação inicial ou ambiente de desenvolvimento.
--
--  Charset: utf8mb4 | Engine: InnoDB | Valores monetários: DECIMAL(10,2)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS admin_logs;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS banners;
DROP TABLE IF EXISTS coupon_usages;
DROP TABLE IF EXISTS favorites;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS carts;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS payment_webhook_events;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS coupons;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS addresses;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Usuários e autenticação
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(120)    NOT NULL,
    email           VARCHAR(180)    NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    role            ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    is_active       TINYINT(1)      NOT NULL DEFAULT 1,
    phone           VARCHAR(30)     DEFAULT NULL,
    last_login_at   DATETIME        DEFAULT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME        DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    selector    CHAR(32)        NOT NULL,
    token_hash  CHAR(64)        NOT NULL,
    expires_at  DATETIME        NOT NULL,
    used_at     DATETIME        DEFAULT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reset_selector (selector),
    KEY idx_reset_user (user_id),
    KEY idx_reset_expires (expires_at),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email        VARCHAR(180)    NOT NULL,
    ip           VARCHAR(45)     NOT NULL,
    success      TINYINT(1)      NOT NULL DEFAULT 0,
    attempted_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_email_time (email, attempted_at),
    KEY idx_login_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Endereços
-- ---------------------------------------------------------------------
CREATE TABLE addresses (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        BIGINT UNSIGNED NOT NULL,
    label          VARCHAR(60)     NOT NULL DEFAULT 'Casa',
    recipient_name VARCHAR(120)    NOT NULL,
    phone          VARCHAR(30)     NOT NULL,
    cep            CHAR(9)         NOT NULL,
    street         VARCHAR(180)    NOT NULL,
    number         VARCHAR(20)     NOT NULL,
    complement     VARCHAR(120)    DEFAULT NULL,
    district       VARCHAR(120)    NOT NULL,
    city           VARCHAR(120)    NOT NULL,
    state          CHAR(2)         NOT NULL,
    is_default     TINYINT(1)      NOT NULL DEFAULT 0,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at     DATETIME        DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_addresses_user (user_id),
    CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Catálogo
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id   BIGINT UNSIGNED DEFAULT NULL,
    name        VARCHAR(120)    NOT NULL,
    slug        VARCHAR(140)    NOT NULL,
    description VARCHAR(500)    DEFAULT NULL,
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order  INT             NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent (parent_id),
    KEY idx_categories_active (is_active),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id          BIGINT UNSIGNED NOT NULL,
    name                 VARCHAR(180)    NOT NULL,
    slug                 VARCHAR(200)    NOT NULL,
    sku                  VARCHAR(60)     NOT NULL,
    short_description    VARCHAR(255)    DEFAULT NULL,
    description          TEXT            NOT NULL,
    price                DECIMAL(10,2)   NOT NULL,
    sale_price           DECIMAL(10,2)   DEFAULT NULL,
    cost_price           DECIMAL(10,2)   DEFAULT NULL,
    stock_quantity       INT             NOT NULL DEFAULT 0,
    low_stock_threshold  INT             NOT NULL DEFAULT 5,
    weight_grams         INT             NOT NULL DEFAULT 0,
    is_active            TINYINT(1)      NOT NULL DEFAULT 1,
    is_featured          TINYINT(1)      NOT NULL DEFAULT 0,
    created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at           DATETIME        DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_category (category_id),
    KEY idx_products_active (is_active),
    KEY idx_products_featured (is_featured),
    KEY idx_products_price (price),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    filename   VARCHAR(255)    NOT NULL,
    alt        VARCHAR(150)    DEFAULT NULL,
    sort_order INT             NOT NULL DEFAULT 0,
    is_primary TINYINT(1)      NOT NULL DEFAULT 0,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_images_product (product_id),
    CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Cupons
-- ---------------------------------------------------------------------
CREATE TABLE coupons (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code               VARCHAR(60)     NOT NULL,
    description        VARCHAR(180)    DEFAULT NULL,
    type               ENUM('percent','fixed') NOT NULL,
    value              DECIMAL(10,2)   NOT NULL,
    min_order_amount   DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    max_uses           INT             DEFAULT NULL,
    used_count         INT             NOT NULL DEFAULT 0,
    max_uses_per_user  INT             NOT NULL DEFAULT 1,
    starts_at          DATETIME        DEFAULT NULL,
    expires_at         DATETIME        DEFAULT NULL,
    is_active          TINYINT(1)      NOT NULL DEFAULT 1,
    created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_coupons_code (code),
    KEY idx_coupons_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pedidos
-- ---------------------------------------------------------------------
CREATE TABLE orders (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code              VARCHAR(24)     NOT NULL,
    user_id           BIGINT UNSIGNED NOT NULL,
    status            ENUM('aguardando_pagamento','pago','em_preparacao','enviado','entregue','cancelado')
                      NOT NULL DEFAULT 'aguardando_pagamento',
    payment_status    ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    payment_method    VARCHAR(40)     NOT NULL DEFAULT 'demo',
    subtotal          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    discount_total    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    shipping_total    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    total             DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    coupon_id         BIGINT UNSIGNED DEFAULT NULL,
    coupon_code       VARCHAR(60)     DEFAULT NULL,
    shipping_method   VARCHAR(20)     NOT NULL DEFAULT 'flat',
    shipping_label    VARCHAR(80)     NOT NULL DEFAULT 'Entrega padrão',
    recipient_name    VARCHAR(120)    NOT NULL,
    recipient_phone   VARCHAR(30)     NOT NULL,
    shipping_cep      CHAR(9)         NOT NULL,
    shipping_street   VARCHAR(180)    NOT NULL,
    shipping_number   VARCHAR(20)     NOT NULL,
    shipping_complement VARCHAR(120)  DEFAULT NULL,
    shipping_district VARCHAR(120)    NOT NULL,
    shipping_city     VARCHAR(120)    NOT NULL,
    shipping_state    CHAR(2)         NOT NULL,
    customer_note     VARCHAR(500)    DEFAULT NULL,
    tracking_code     VARCHAR(80)     DEFAULT NULL,
    paid_at           DATETIME        DEFAULT NULL,
    shipped_at        DATETIME        DEFAULT NULL,
    delivered_at      DATETIME        DEFAULT NULL,
    canceled_at       DATETIME        DEFAULT NULL,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_code (code),
    KEY idx_orders_user (user_id),
    KEY idx_orders_status (status),
    KEY idx_orders_created (created_at),
    KEY idx_orders_payment_status (payment_status),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id      BIGINT UNSIGNED NOT NULL,
    product_id    BIGINT UNSIGNED DEFAULT NULL,
    product_name  VARCHAR(180)    NOT NULL,
    product_sku   VARCHAR(60)     NOT NULL,
    product_image VARCHAR(255)    DEFAULT NULL,
    unit_price    DECIMAL(10,2)   NOT NULL,
    quantity      INT             NOT NULL,
    line_total    DECIMAL(10,2)   NOT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_product (product_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id            BIGINT UNSIGNED NOT NULL,
    provider            VARCHAR(30)     NOT NULL DEFAULT 'demo',
    method              VARCHAR(30)     NOT NULL DEFAULT 'demo',
    provider_payment_id VARCHAR(150)    DEFAULT NULL,
    amount              DECIMAL(10,2)   NOT NULL,
    status              ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    payload             JSON            DEFAULT NULL,
    paid_at             DATETIME        DEFAULT NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_provider_payment (provider_payment_id),
    KEY idx_payments_order (order_id),
    KEY idx_payments_status (status),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_webhook_events (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider     VARCHAR(30)     NOT NULL,
    event_id     VARCHAR(150)    NOT NULL,
    payload      JSON            DEFAULT NULL,
    processed_at DATETIME        DEFAULT NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_webhook_event (provider, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_history (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id   BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(30)    DEFAULT NULL,
    to_status  VARCHAR(30)     NOT NULL,
    note       VARCHAR(255)    DEFAULT NULL,
    changed_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_history_order (order_id),
    CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_history_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id        BIGINT UNSIGNED NOT NULL,
    order_id          BIGINT UNSIGNED DEFAULT NULL,
    user_id           BIGINT UNSIGNED DEFAULT NULL,
    type              ENUM('entrada','saida','ajuste','venda','cancelamento') NOT NULL,
    quantity          INT             NOT NULL,
    previous_quantity INT             NOT NULL,
    new_quantity      INT             NOT NULL,
    reason            VARCHAR(255)    DEFAULT NULL,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stock_product (product_id),
    KEY idx_stock_order (order_id),
    KEY idx_stock_created (created_at),
    CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
    CONSTRAINT fk_stock_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Carrinho e favoritos
-- ---------------------------------------------------------------------
CREATE TABLE carts (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token      CHAR(64)        NOT NULL,
    user_id    BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_carts_token (token),
    KEY idx_carts_user (user_id),
    CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cart_id    BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity   INT             NOT NULL DEFAULT 1,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_items (cart_id, product_id),
    KEY idx_cart_items_product (product_id),
    CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorites (
    user_id    BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    KEY idx_favorites_product (product_id),
    CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_favorites_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupon_usages (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_id  BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    order_id   BIGINT UNSIGNED NOT NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_coupon_order (coupon_id, order_id),
    KEY idx_coupon_usages_user (user_id),
    CONSTRAINT fk_coupon_usages_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE,
    CONSTRAINT fk_coupon_usages_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_coupon_usages_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Conteúdo e configurações
-- ---------------------------------------------------------------------
CREATE TABLE banners (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title      VARCHAR(150)    NOT NULL,
    subtitle   VARCHAR(255)    DEFAULT NULL,
    image      VARCHAR(255)    DEFAULT NULL,
    link       VARCHAR(255)    DEFAULT NULL,
    position   VARCHAR(20)     NOT NULL DEFAULT 'hero',
    is_active  TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order INT             NOT NULL DEFAULT 0,
    starts_at  DATETIME        DEFAULT NULL,
    ends_at    DATETIME        DEFAULT NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_banners_active (is_active, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    name       VARCHAR(80) NOT NULL,
    value      TEXT        DEFAULT NULL,
    updated_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED DEFAULT NULL,
    action      VARCHAR(80)     NOT NULL,
    entity_type VARCHAR(60)     DEFAULT NULL,
    entity_id   BIGINT UNSIGNED DEFAULT NULL,
    details     JSON            DEFAULT NULL,
    ip          VARCHAR(45)     DEFAULT NULL,
    user_agent  VARCHAR(255)    DEFAULT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_logs_user (user_id),
    KEY idx_admin_logs_created (created_at),
    CONSTRAINT fk_admin_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
