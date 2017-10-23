<?php
namespace Coderity\Wallet;

use Cartalyst\Stripe\Stripe;
use Stripe\Charge as StripeCharge;
use Laravel\Cashier\Billable as Cashier;

trait Billable
{
    use Cashier;

    /**
     * Adds a card
     * @param mixed Either an array of params as per the generateToken method or simply a token
     * @param bool $setAsDefault
     * @return array
     */
    public function addCard($params, $setAsDefault = false)
    {
        if (is_array($params)) {
            $result = $this->generateToken($params);

            if ($result['status'] === 'error') {
                return $result;
            }

            $token = $result['token'];
        } else {
            $token = $params;
        }

        if (!$this->stripe_id) {
            $customer = $this->createAsStripeCustomer($token);

            $card = $this->getDefaultCard();

            return [
                'status' => 'success',
                'cardId' => $card['id']
            ];
        }

        $customerId = $this->stripe_id;

        try {
            $stripe = new Stripe(Billable::getStripeKey());

            $card = $stripe->cards()
                ->create($customerId, $token);

            if ($setAsDefault) {
                // lets set this card as the default
                $this->updateDefaultCard($card['id']);
            }

            return [
                'status' => 'success',
                'cardId' => $card['id']
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  int  $amount
     * @param  array  $options
     * @return \Stripe\Charge
     *
     * @throws \InvalidArgumentException
     */
    public function charge($amount, array $options = [])
    {
        $options = array_merge([
            'currency' => $this->preferredCurrency(),
        ], $options);

        $options['amount'] = $amount;

        if (! array_key_exists('source', $options) && $this->stripe_id) {
            $options['customer'] = $this->stripe_id;
        }

        if (! array_key_exists('source', $options) && ! array_key_exists('customer', $options)) {
            throw new InvalidArgumentException('No payment source provided.');
        }

        if (array_key_exists('cardId', $options)) {
            $options['source'] = $options['cardId'];
            unset($options['cardId']);
        }

        return StripeCharge::create($options, ['api_key' => $this->getStripeKey()]);
    }

    /**
     * Deletes a card
     * @param  string $cardId
     * @return array
     */
    public function deleteCard($cardId)
    {
        if (!$this->stripe_id) {
            return [
                'status' => 'error',
                'message' => 'Invalid StripeId'
            ];
        }

        try {
            $stripe = new Stripe(Billable::getStripeKey());

            $card = $stripe->cards()
                ->delete($this->stripe_id, $cardId);

            return [
                'status' => 'success',
                'cardId' => $card['id']
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generates a Stripe Token
     * @param  array  $params Expects: cardNumber, expiryMonth, expiryYear, cvc
     * @return array
     */
    public function generateToken(array $params)
    {
        $stripe = new Stripe(Billable::getStripeKey());

        try {
            $attributes = [
                'card' => [
                    'number'    => $params['cardNumber'],
                    'exp_month' => $params['expiryMonth'],
                    'cvc'       => $params['cvc'],
                    'exp_year'  => $params['expiryYear'],
                ],
            ];

            $token = $stripe->tokens()->create($attributes);

            return [
                'status' => 'success',
                'token' => $token['id']
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Gets a card
     * @param  string $cardId
     * @return Laravel\Cashier\Stripe\Card
     */
    public function getCard($cardId)
    {
        if (!$this->stripe_id) {
            return null;
        }

        $customer = $this->asStripeCustomer();

        foreach ($customer->sources->data as $card) {
            if ($card->id === $cardId) {
                return $card;
            }
        }

        return null;
    }

    /**
     * Returns the default card
     * @return Laravel\Cashier\Stripe\Card
     */
    public function getDefaultCard()
    {
        if (!$this->stripe_id) {
            return null;
        }

        $customer = $this->asStripeCustomer();

        foreach ($customer->sources->data as $card) {
            if ($card->id === $customer->default_source) {
                return $card;
            }
        }

        return null;
    }

    /**
     * Gets a users stripeId
     * Or generates a new one if the user doesn't have one
     * @return string
     */
    public function getStripeId()
    {
        if ($this->stripe_id) {
            return $this->stripe_id;
        }

        $customer = $this->createAsStripeCustomer(null);

        return $customer->id;
    }

    /**
     * Begin creating a new subscription.
     *
     * @param  string  $subscription
     * @param  string  $plan
     * @return \Laravel\Cashier\SubscriptionBuilder
     */
    public function newSubscription($subscription, $plan)
    {
        return new SubscriptionBuilder($this, $subscription, $plan);
    }

    /**
     * Updates the default card
     * @param string $cardId The cardId to update to the default card
     * @return $this
     */
    public function updateDefaultCard($cardId)
    {
        // first lets update the default source for the stripe customer
        $customer = $this->asStripeCustomer();
        $customer->default_source = $cardId;
        $result = $customer->save();

        // now lets update the users default card
        $card = $this->getCard($cardId);

        $this->card_brand = $card->brand;
        $this->card_last_four = $card->last4;

        $this->save();

        return $this;
    }
}
