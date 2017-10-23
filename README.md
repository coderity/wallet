# Coderity Wallet

## Introduction

Coderity Wallet extends Laravel Cashier allowing users to have multiple credit cards with multiple subscriptions.

Coderity Wallet currently only works with Stripe.

## Installation

Wallet follows closely with the [Laravel Cashier Installation Guide](https://laravel.com/docs/5.5/billing#introduction) - with some subtle differences.

#### Composer

First, add the Wallet package for Stripe to your dependencies:

    composer require "coderity/wallet":"~1.0"

#### Service Provider

If your version of Laravel is version 5.5 or greater, you can skip the following step.

Next, register the `Coderity\Wallet\WalletServiceProvider` ervice provider in your `config/app.php` configuration file.

#### Database Migrations

Before using Wallet, we'll also need to prepare the database. We need to add several columns to your `users` table and create a new `subscriptions` table to hold all of our customer's subscriptions:

    Schema::table('users', function ($table) {
        $table->string('stripe_id')->nullable();
        $table->string('card_brand')->nullable();
        $table->string('card_last_four')->nullable();
        $table->timestamp('trial_ends_at')->nullable();
    });

    Schema::create('subscriptions', function ($table) {
        $table->increments('id');
        $table->integer('user_id');
        $table->string('name');
        $table->string('stripe_id');
        $table->string('stripe_plan');
        $table->integer('quantity');
        $table->timestamp('trial_ends_at')->nullable();
        $table->timestamp('ends_at')->nullable();
        $table->timestamps();
    });

Once the migrations have been created, run the `migrate` Artisan command.

#### Wallet Model

Next, add the `Wallet` trait to your model definition. This trait provides various methods to allow you to perform common billing tasks, such as creating subscriptions, applying coupons, and updating credit card information:

    use Coderity\Wallet\Wallet;

    class User extends Authenticatable
    {
        use Wallet;
    }

#### API Keys

Finally, you should configure your Stripe key in your `services.php` configuration file. You can retrieve your Stripe API keys from the Stripe control panel:

    'stripe' => [
        'model'  => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

## Examples

## Running Wallet's Tests Locally

You will need to set the following details locally and on your Stripe account in order to run the Wallet unit tests:

### Environment

#### .env

    STRIPE_KEY=
    STRIPE_SECRET=
    STRIPE_MODEL=User

### Stripe

#### Plans

    * monthly-10-1 ($10)

## License

Coderity Wallet is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)