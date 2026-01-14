-- Таблица клиентов
CREATE TABLE client (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255)
);

INSERT INTO client (name, email) VALUES
('Иван Иванов', 'ivan@mail.com'),
('Мария Петрова', 'maria@mail.com'),
('Алексей Смирнов', 'alex@mail.com');

-- Таблица товаров
CREATE TABLE product (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    composition TEXT,
    price NUMERIC(10,2),
    gender VARCHAR(50),
    quantity INTEGER,
    size VARCHAR(10),
    color VARCHAR(50),
    season VARCHAR(20),
    clothing_type VARCHAR(50)
);

INSERT INTO product (name, composition, price, gender, quantity, size, color, season, clothing_type) VALUES
('Футболка', '100% хлопок', 1200.00, 'Мужская', 10, 'M', 'Белый', 'Лето', 'Футболка'),
('Джинсы', '98% хлопок, 2% эластан', 2500.00, 'Женская', 5, 'S', 'Синий', 'Весна', 'Джинсы'),
('Куртка', 'Полиэстер', 5500.00, 'Мужская', 3, 'L', 'Черный', 'Зима', 'Куртка');

-- Таблица корзины
CREATE TABLE cart (
    id SERIAL PRIMARY KEY,
    client_id INTEGER REFERENCES client(id),
    product_id INTEGER REFERENCES product(id),
    quantity INTEGER
);

INSERT INTO cart (client_id, product_id, quantity) VALUES
(1, 1, 2),
(2, 3, 1),
(3, 2, 1);

-- Таблица заказов
CREATE TABLE orders (
    id SERIAL PRIMARY KEY,
    client_id INTEGER REFERENCES client(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20),
    total_price NUMERIC(10,2),
    shipping_address TEXT
);

INSERT INTO orders (client_id, status, total_price, shipping_address) VALUES
(1, 'В обработке', 2400.00, 'Москва, ул. Ленина 10'),
(2, 'Доставлен', 5500.00, 'Санкт-Петербург, пр. Невский 25');

-- Таблица элементов заказов
CREATE TABLE order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER REFERENCES orders(id),
    product_id INTEGER REFERENCES product(id),
    quantity INTEGER,
    price NUMERIC(10,2),
    total_price NUMERIC(10,2)
);

INSERT INTO order_items (order_id, product_id, quantity, price, total_price) VALUES
(1, 1, 2, 1200.00, 2400.00),
(2, 3, 1, 5500.00, 5500.00);
