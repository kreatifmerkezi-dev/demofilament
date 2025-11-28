<?php

namespace Database\Seeders;

use App\Filament\Resources\Shop\Orders\OrderResource;
use App\Models\Address;
use App\Models\Blog\Author;
use App\Models\Blog\Category as BlogCategory;
use App\Models\Blog\Post;
use App\Models\Comment;
use App\Models\Shop\Brand;
use App\Models\Shop\Category as ShopCategory;
use App\Models\Shop\Customer;
use App\Models\Shop\Order;
use App\Models\Shop\OrderItem;
use App\Models\Shop\Payment;
use App\Models\Shop\Product;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Helper\ProgressBar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::raw("SET time_zone='+00:00'");

        // Clear images
        Storage::deleteDirectory('public');

        /**
         * -----------------------------------------------------
         * ADMIN USER — DO NOT USE PROGRESS BAR HERE
         * -----------------------------------------------------
         */
        $this->command->warn(PHP_EOL . 'Creating admin user...');

        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'admin@filamentphp.com',
            'password' => Hash::make('demo.Filament@2021!'),
        ]);

        $this->command->info('Admin user created.');

        /**
         * -----------------------------------------------------
         * SHOP BRANDS
         * -----------------------------------------------------
         */
        $this->command->warn(PHP_EOL . 'Creating shop brands...');
        $brands = $this->withProgressBar(20, fn () =>
            Brand::factory()
                ->has(Address::factory()->count(rand(1, 3)))
                ->create()
        );
        Brand::query()->update(['sort' => new Expression('id')]);
        $this->command->info('Shop brands created.');

        /**
         * -----------------------------------------------------
         * SHOP CATEGORIES
         * -----------------------------------------------------
         */
        $this->command->warn(PHP_EOL . 'Creating shop categories...');
        $categories = $this->withProgressBar(20, fn () =>
            ShopCategory::factory()
                ->has(
                    ShopCategory::factory()->count(3),
                    'children'
                )
                ->create()
        );
        $this->command->info('Shop categories created.');

        /**
         * -----------------------------------------------------
         * SHOP CUSTOMERS
         * -----------------------------------------------------
         */
        $this->command->warn(PHP_EOL . 'Creating shop customers...');
        $customers = $this->withProgressBar(1000, fn () =>
            Customer::factory()
                ->has(Address::factory()->count(rand(1, 3)))
                ->create()
        );
        $this->command->info('Shop customers created.');

        /**
         * -----------------------------------------------------
         * SHOP PRODUCTS
         * -----------------------------------------------------
         */
        $this->command->warn(PHP_EOL . 'Creating shop products...');
        $products = $this->withProgressBar(50, fn () =>
            Product::factory()
                ->sequence(fn () => [
                    'shop_brand_id' => $brands->random()->id
                ])
                ->hasAttached(
                    $categories->random(rand(3, 6)),
                    ['created_at' => now(), 'updated_at' => now()]
                )
                ->has(
                    Comment::factory()->count(rand(10, 20))
                        ->state(fn () => [
                            'customer_id' => $customers->random()->id
                        ])
                )
                ->create()
        );
        $this->command->info('Shop products created.');

        /**
         * -----------------------------------------------------
         * ORDERS
         * -----------------------------------------------------
         */
        $this->command->warn(PHP_EOL . 'Creating orders...');
        $orders = $this->withProgressBar(1000, fn () =>
            Order::factory()
                ->sequence(fn () => [
                    'shop_customer_id' => $customers->random()->id
                ])
                ->has(Payment::factory()->count(rand(1, 3)))
                ->has(
                    OrderItem::factory()->count(rand(2, 5))
                        ->state(fn () => [
                            'shop_product_id' => $products->random()->id
                        ]),
                    'items'
                )
                ->create()
        );

        // Notify random orders
        foreach ($orders->random(rand(5, 8)) as $order) {
            Notification::make()
                ->title('New order')
                ->icon('heroicon-o-shopping-bag')
                ->body("{$order->customer->name} ordered {$order->items->count()} products.")
                ->actions([
                    Action::make('View')
                        ->url(OrderResource::getUrl('edit', ['record' => $order])),
                ])
                ->sendToDatabase($user);
        }

        $this->command->info('Shop orders created.');

        /**
         * -----------------------------------------------------
         * BLOG CATEGORIES
         * -----------------------------------------------------
         */
        $this->command->warn(PHP_EOL . 'Creating blog categories...');
        $blogCategories = $this->withProgressBar(20, fn () =>
            BlogCategory::factory()->create()
        );
        $this->command->info('Blog categories created.');

        /**
         * -----------------------------------------------------
         * BLOG AUTHORS + POSTS
         * -----------------------------------------------------
         */
        $this->command->warn(PHP_EOL . 'Creating blog authors and posts...');
        $this->withProgressBar(20, fn () =>
            Author::factory()
                ->has(
                    Post::factory()->count(5)
                        ->has(
                            Comment::factory()->count(rand(5, 10))
                                ->state(fn () => [
                                    'customer_id' => $customers->random()->id
                                ])
                        )
                        ->state(fn () => [
                            'blog_category_id' => $blogCategories->random()->id
                        ]),
                    'posts'
                )
                ->create()
        );
        $this->command->info('Blog authors and posts created.');
    }

    /**
     * PROGRESS BAR UTILITY
     */
    protected function withProgressBar(int $amount, Closure $factory): Collection
    {
        $progressBar = new ProgressBar($this->command->getOutput(), $amount);

        $progressBar->start();

        $items = new Collection;

        foreach (range(1, $amount) as $_) {
            $items = $items->merge(
                $factory()
            );
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->getOutput()->writeln('');

        return $items;
    }
}
