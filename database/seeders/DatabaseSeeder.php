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

        Storage::deleteDirectory('public');

        /**
         * -----------------------------------------------------
         * ADMIN USER
         * -----------------------------------------------------
         */
        $this->command->warn(PHP_EOL . 'Creating admin user...');

        $user = User::create([
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
        $brands = $this->withProgressBar(20, function () {
            $brand = Brand::create([
                'name' => fake()->company(),
                'slug' => fake()->slug(),
            ]);

            foreach (range(1, rand(1, 3)) as $i) {
                Address::create([
                    'addressable_type' => Brand::class,
                    'addressable_id' => $brand->id,
                    'address' => fake()->address(),
                ]);
            }

            return $brand;
        });

        Brand::query()->update(['sort' => new Expression('id')]);

        $this->command->info('Shop brands created.');

        /**
         * -----------------------------------------------------
         * SHOP CATEGORIES
         * -----------------------------------------------------
         */
        $this->command->warn(PHP_EOL . 'Creating shop categories...');
        $categories = $this->withProgressBar(20, function () {
            $parent = ShopCategory::create([
                'name' => fake()->word(),
                'slug' => fake()->slug()
            ]);

            foreach (range(1, 3) as $i) {
                ShopCategory::create([
                    'name' => fake()->word(),
                    'slug' => fake()->slug(),
                    'parent_id' => $parent->id,
                ]);
            }

            return $parent;
        });
        $this->command->info('Shop categories created.');

        /**
         * -----------------------------------------------------
         * SHOP CUSTOMERS
         * -----------------------------------------------------
         */
        $this->command->warn(PHP_EOL . 'Creating shop customers...');
        $customers = $this->withProgressBar(1000, function () {
            $customer = Customer::create([
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
            ]);

            foreach (range(1, rand(1, 3)) as $i) {
                Address::create([
                    'addressable_type' => Customer::class,
                    'addressable_id' => $customer->id,
                    'address' => fake()->address(),
                ]);
            }

            return $customer;
        });
        $this->command->info('Shop customers created.');

        /**
         * -----------------------------------------------------
         * SHOP PRODUCTS
         * -----------------------------------------------------
         */
        $this->command->warn(PHP_EOL . 'Creating shop products...');
        $products = $this->withProgressBar(50, function () use ($brands, $categories, $customers) {
            $product = Product::create([
                'name' => fake()->productName(),
                'slug' => fake()->slug(),
                'price' => rand(10, 200),
                'shop_brand_id' => $brands->random()->id,
            ]);

            $product->categories()->attach(
                $categories->random(rand(3, 6))->pluck('id')->toArray()
            );

            foreach (range(10, 20) as $i) {
                Comment::create([
                    'content' => fake()->paragraph(),
                    'customer_id' => $customers->random()->id,
                    'commentable_id' => $product->id,
                    'commentable_type' => Product::class,
                ]);
            }

            return $product;
        });
        $this->command->info('Shop products created.');

        /**
         * -----------------------------------------------------
         * ORDERS
         * -----------------------------------------------------
         */
        $this->command->warn(PHP_EOL . 'Creating orders...');
        $orders = $this->withProgressBar(1000, function () use ($customers, $products) {
            $order = Order::create([
                'shop_customer_id' => $customers->random()->id,
                'total' => 0,
            ]);

            foreach (range(1, rand(1, 3)) as $i) {
                Payment::create([
                    'order_id' => $order->id,
                    'amount' => rand(10, 100),
                ]);
            }

            $total = 0;
            foreach (range(2, 5) as $i) {
                $product = $products->random();
                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'shop_product_id' => $product->id,
                    'quantity' => 1,
                    'price' => $product->price,
                ]);
                $total += $item->price;
            }

            $order->update(['total' => $total]);

            return $order;
        });

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
        $blogCategories = $this->withProgressBar(20, function () {
            return BlogCategory::create([
                'name' => fake()->word(),
                'slug' => fake()->slug(),
            ]);
        });
        $this->command->info('Blog categories created.');

        /**
         * -----------------------------------------------------
         * BLOG AUTHORS & POSTS
         * -----------------------------------------------------
         */
        $this->command->warn(PHP_EOL . 'Creating blog authors and posts...');
        $this->withProgressBar(20, function () use ($blogCategories, $customers) {
            $author = Author::create([
                'name' => fake()->name(),
            ]);

            foreach (range(1, 5) as $i) {
                $post = Post::create([
                    'title' => fake()->sentence(),
                    'slug' => fake()->slug(),
                    'content' => fake()->paragraph(5),
                    'author_id' => $author->id,
                    'blog_category_id' => $blogCategories->random()->id,
                ]);

                foreach (range(5, 10) as $i) {
                    Comment::create([
                        'content' => fake()->paragraph(),
                        'customer_id' => $customers->random()->id,
                        'commentable_id' => $post->id,
                        'commentable_type' => Post::class,
                    ]);
                }
            }

            return $author;
        });

        $this->command->info('Blog authors and posts created.');
    }

    protected function withProgressBar(int $amount, Closure $factory): Collection
    {
        $progressBar = new ProgressBar($this->command->getOutput(), $amount);

        $progressBar->start();

        $items = new Collection;

        foreach (range(1, $amount) as $_) {
            $items->push($factory());
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->getOutput()->writeln('');

        return $items;
    }
}
