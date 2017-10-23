# Coderity Wallet

## Introduction

Coderity Wallet extends [Laravel Cashier](http://github.com/laravel/cashier) allowing users to have multiple credit cards with multiple subscriptions, along with the ability to charge different cards as needed.

Coderity Wallet still contains all the features of [Laravel Cashier](http://github.com/laravel/cashier), with extra methods available!

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

#### Billable Model

Next, add the `Billable` trait to your model definition. This trait provides various methods to allow you to perform common billing tasks, such as creating subscriptions, applying coupons, and updating credit card information:

    use Coderity\Wallet\Billable;

    class User extends Authenticatable
    {
        use Billable;
    }

#### API Keys

Finally, you should configure your Stripe key in your `services.php` configuration file. You can retrieve your Stripe API keys from the Stripe control panel:

    'stripe' => [
        'model'  => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

## Documentation

You should be familiar with [Laravel Cashier Installation Guide](https://laravel.com/docs/5.5/billing) to see all of the methods and features available.

The following highlights the specific methods of Coderity Wallet.

#### Adding a Credit Card

You can add a credit card easily for a user by passing the `addCard()` method:

```php
$user = User::find(1);

$user->addCard([
    'cardNumber' => 4242424242424242,
    'expiryMonth' => 12,
    'expiryYear' => 2021,
    'cvc' => 123
]);
```
If you would like to set the new card as the default card, you can pass true as the second parameter:

```php
$user->addCard([
    'cardNumber' => 4242424242424242,
    'expiryMonth' => 12,
    'expiryYear' => 2021,
    'cvc' => 123
], true);
```

If you already have a token generated, you can also pass a token as the first parameter:

```php
$user->addCard($token);
```

#### Generate a Token

If you need to generate a token or even validate a credit card, use the `generateToken()` method:

```php
$result = $user->generateToken([
    'cardNumber' => 4242424242424242,
    'expiryMonth' => 12,
    'expiryYear' => 2021,
    'cvc' => 123
]);

$token = $result['token'];
```

#### Get all Cards

You can see all the cards for a user by passing the `cards()` method:

```php
$user->cards();
```

An array of cards will be returned.  Note, the card ID (`$card->id`) will be a prefixed with `card_`, e.g. `card_1BGBDfLBsAc3LtzZbIEQ8xpF`.  This is the ID you will need to use the card in other methods.

#### Get a Card

To get a specific card, you can pass the card ID to the `getCard()` method:

```php
$user->getCard($cardID);
```

#### Charging with a Specific Card

The `charge()` method works the same [Laravel Cashier](https://laravel.com/docs/5.5/billing#single-charges) with the following additional parameter:

```php
$this->user->charge(100, [
     'cardId' => $cardId
]);
```

This parameter will charge the specific card with $100 in this case.

#### Charging without a Subscription

Coderity Wallet also makes doing simple charging very easy.  By passing two methods, you can easily make a one off charge (without needing the user to have signed up for a subscription):

```php
$card = $user->addCard([
    'cardNumber' => 4242424242424242,
    'expiryMonth' => 12,
    'expiryYear' => 2021,
    'cvc' => 123
]);

$this->user->charge(100, [
     'cardId' => $card['cardId']
]);
```

#### Creating a Subscription with a Specific Card

You can create a subscription with a specific card, by including the `useCard()` method, before calling `create()` when adding a subscription.

```php
$this->user->newSubscription('main', 'monthly-10-1')
    ->trialDays(10)
    ->useCard($cardId)
    ->create();
```
Note that the currently functionality will actually set this card as the default card for all the user's subscriptions - this is how Stripe currently handles multiple subscriptions for a customer.

#### Update Default Card

Of course, you can update the default card at any stage by using the `updateDefaultCard()` method:

```php
$user->updateDefaultCard($cardId);
```

#### Get Default Card

If you want to get the users default card, simple use the `getDefaultCard()` method:

```php
$card = $user->getDefaultCard();
```

#### Delete a Specific Card

You can delete a specific card by passing the card ID to the `deleteCard()` method:

```php
$user->deleteCard($cardId);
```
For more use cases, please refer to the [Unit Tests](https://github.com/coderity/wallet/blob/master/tests/WalletTest.php).

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

## Contributing

Please read the [Contributing Guide](https://github.com/coderity/wallet/blob/master/contributing.md) if you would like to make any suggestions for improvements to Coderity Wallet.

## License

Coderity Wallet is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
