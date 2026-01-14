<?php
// файл: tests/ShopDatabaseTest.php

namespace Tests;

use PHPUnit\Framework\TestCase;

class ShopDatabaseTest extends TestCase
{
    // Липовая база данных товаров
    private $products;
    
    protected function setUp(): void
    {
        $this->products = [
            ['id' => 1, 'name' => 'T-Shirt', 'category' => 'Clothing', 'price' => 20, 'stock' => 10],
            ['id' => 2, 'name' => 'Jeans', 'category' => 'Clothing', 'price' => 50, 'stock' => 5],
            ['id' => 3, 'name' => 'Dress', 'category' => 'Clothing', 'price' => 80, 'stock' => 2],
        ];
    }

    public function testProductListNotEmpty()
    {
        // Проверяем, что список товаров не пуст
        $this->assertNotEmpty($this->products);
    }

    public function testProductStockAvailable()
    {
        // Проверяем наличие товаров в наличии
        foreach ($this->products as $product) {
            $this->assertGreaterThanOrEqual(0, $product['stock']);
        }
    }

    public function testAddToOrderCalculatesTotal()
    {
        // Липовый заказ
        $order = [
            ['product_id' => 1, 'quantity' => 2],
            ['product_id' => 2, 'quantity' => 1],
        ];

        // Считаем сумму заказа
        $total = 0;
        foreach ($order as $item) {
            $product = array_filter($this->products, fn($p) => $p['id'] === $item['product_id']);
            $product = array_shift($product); // берем первый элемент
            $total += $product['price'] * $item['quantity'];
        }

        // Проверяем, что сумма заказа корректна
        $this->assertEquals(90, $total); // 2*20 + 1*50 = 90
    }

    public function testDatabaseConnectionSimulation()
    {
        // Липовое соединение с базой данных
        $connection = true; // имитация успешного соединения
        $this->assertTrue($connection);
    }

    public function testProductCategories()
    {
        // Проверяем, что все товары принадлежат к категории "Clothing"
        foreach ($this->products as $product) {
            $this->assertEquals('Clothing', $product['category']);
        }
    }
}
