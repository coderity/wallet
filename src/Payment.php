<?php
namespace Coderity\Payment;

use Cartalyst\Stripe\Stripe;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Stripe\Card as StripeCard;
use Laravel\Cashier\Stripe\Token as StripeToken;
use Laravel\Cashier\Stripe\Customer as StripeCustomer;

trait Payment
{
    use Billable;

    /**
     * Adds a card
     * @param mixed Either an array of params as per the generateToken method or simply a token
     * @return array
     */
    public function addCard($params)
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
            $token = $stripe->tokens()->create([
                'card' => [
                    'number'    => $params['cardNumber'],
                    'exp_month' => $params['expiryMonth'],
                    'cvc'       => $params['cvc'],
                    'exp_year'  => $params['expiryYear'],
                ],
            ]);

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
     * Updates the default card
     * @param string $cardId The cardId to update to the default card
     * @return $this
     */
    public function updateDefaultCard($cardId)
    {
        $card = $this->getCard($cardId);

        return $this->fillCardDetails($card);
    }
}