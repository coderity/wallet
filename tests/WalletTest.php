<?php

namespace Coderity\Wallet\Tests;

use Faker\Factory;
use Illuminate\Http\Request;
use PHPUnit_Framework_TestCase;
use Coderity\Wallet\Tests\Fixtures\User;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model as Eloquent;

class WalletTest extends PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        if (file_exists(__DIR__.'/../.env')) {
            $dotenv = new \Dotenv\Dotenv(__DIR__.'/../');
            $dotenv->load();
        }
    }

    public function setUp()
    {
        Eloquent::unguard();

        $db = new DB;
        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $db->bootEloquent();
        $db->setAsGlobal();

        $this->schema()->create('users', function ($table) {
            $table->increments('id');
            $table->string('email');
            $table->string('name');
            $table->string('stripe_id')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_last_four')->nullable();
            $table->timestamps();
        });

        $this->schema()->create('subscriptions', function ($table) {
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

        $faker = Factory::create();

        $this->user = User::create([
            'email' => $faker->email,
            'name' => $faker->name,
        ]);
    }

    public function tearDown()
    {
        $this->schema()->drop('users');
        $this->schema()->drop('subscriptions');
    }

    /**
     * Add Card test
     *
     * @return void
     */
    public function testAddCard()
    {
        // lets test with passing a token
        $result = $this->user->addCard('tok_ie');
        $this->assertEquals('success', $result['status']);
        $this->assertNotNull($result['cardId']);

        // lets test with passing a full card number
        // we will also update this card to be the default
        $result = $this->user->addCard([
            'cardNumber' => '4242424242424242',
            'expiryMonth' => '01',
            'expiryYear' => date('Y') + 1,
            'cvc' => 123
        ], true);

        $this->assertEquals('success', $result['status']);
        $this->assertNotNull($result['cardId']);

        // lets check if this card is the default
        $defaultCard = $this->user->getDefaultCard();
        $this->assertEquals($result['cardId'], $defaultCard->id);

        // lets test a declined token
        $result = $this->user->addCard('tok_chargeDeclined');
        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Your card was declined.', $result['message']);

        // lets test an expired card
        $result = $this->user->addCard('tok_chargeDeclinedExpiredCard');
        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Your card has expired.', $result['message']);
    }

    public function testCards()
    {
        $this->user->addCard('tok_ca');
        $this->user->addCard('tok_br');
        $this->user->addCard('tok_mx');

        $result = $this->user->cards();
        $this->assertCount(3, $result);
    }

    public function testChargeWithCard()
    {
        // lets add a default card
        $card = $this->user->addCard('tok_ie');

        // and now a default card
        $this->user->addCard('tok_mx', true);

        // lets charge with the non default card
        $result = $this->user->charge(100, [
            'cardId' => $card['cardId']
        ]);

        $this->assertEquals($card['cardId'], $result->source->id);

        $this->assertEquals(100, $result->amount);
    }

    public function testDeleteCard()
    {
        // lets try to delete a card
        // when the user has no stripe_id set
        $result = $this->user->deleteCard('invalidId');
        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Invalid StripeId', $result['message']);

        // lets add a card, then delete it
        $card = $this->user->addCard('tok_ie');

        $result = $this->user->deleteCard($card['cardId']);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals($card['cardId'], $result['cardId']);

        $cards = $this->user->cards();
        $this->assertCount(0, $cards);

        // lets try to delete an invalid cardId
        $result = $this->user->deleteCard('invalidId');
        $this->assertEquals('error', $result['status']);
        $this->assertEquals('No such source: invalidId', $result['message']);
    }

    public function testGenerateToken()
    {
        // lets start by testing a valid credit card
        $result = $this->user->generateToken([
            'cardNumber' => '4242424242424242',
            'expiryMonth' => '01',
            'expiryYear' => date('Y') + 1,
            'cvc' => 123
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertNotNull($result['token']);

        // lets test an expired year
        $result = $this->user->generateToken([
            'cardNumber' => '4242424242424242',
            'expiryMonth' => '01',
            'expiryYear' => date('Y') - 1,
            'cvc' => 123
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Your card\'s expiration year is invalid.', $result['message']);

        // lets test an invalid card number
        $result = $this->user->generateToken([
            'cardNumber' => '1234123412341234',
            'expiryMonth' => '01',
            'expiryYear' => date('Y') + 1,
            'cvc' => 123
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Your card number is incorrect.', $result['message']);
    }

    public function testGetCard()
    {
        $result = $this->user->addCard('tok_br');

        $card = $this->user->getCard($result['cardId']);

        $this->assertNotEmpty($card->last4);
        $this->assertEquals($result['cardId'], $card->id);
    }

    public function testGetDefaultCard()
    {
        $this->user->addCard('tok_ie');
        $result = $this->user->getDefaultCard();

        $this->assertNotNull($result->last4);
        $this->assertEquals($result->last4, $this->user->card_last_four);
    }

    public function testGetStripeId()
    {
        // lets see if a stripe_id gets created
        $this->user->getStripeId();
        $this->assertNotNull($this->user->stripe_id);

        // lets see if an existing stripe_id is returned correctly
        $rand = mt_rand(0, 99999);

        $this->user->stripe_id = $rand;
        $this->user->save();

        $customerId = $this->user->getStripeId();
        $this->assertEquals($rand, $customerId);
    }

    public function testNewSubscriptionWithCard()
    {
        // lets create a card
        $card = $this->user->addCard('tok_ie');

        // now set a default
        $this->user->addCard('tok_mx', true);

        // now lets create a subscription with the original card
        $result = $this->user->newSubscription('main', 'monthly-10-1')
            ->trialDays(10)
            ->useCard($card['cardId'])
            ->create();

        $this->assertEquals('main', $result->name);
    }

    public function testUpdateDefaultCard()
    {
        // lets create a card
        // which will be set to the default card
        $this->user->addCard('tok_ie');
        $this->assertNotNull($this->user->card_last_four);

        // now lets add another card
        $result = $this->user->addCard('tok_mx');

        // and lets set that card as the new default
        $this->user->updateDefaultCard($result['cardId']);

        // lets get the card and check its been set as the default
        $card = $this->user->getCard($result['cardId']);
        $this->assertEquals($card->last4, $this->user->card_last_four);

        // and lastly check that this is now set as the default card
        $defaultCard = $this->user->getDefaultCard();
        $this->assertEquals($card->last4, $defaultCard->last4);
    }

    /**
     * Schema Helpers.
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }

    protected function connection()
    {
        return Eloquent::getConnectionResolver()->connection();
    }
}
