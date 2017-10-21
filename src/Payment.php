<?php
namespace Coderity\Payment;

use Cartalyst\Stripe\Stripe;

trait Payment
{
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
            $customerId = $this->createStripeId();
        } else {
            $customerId = $this->stripe_id;
        }

        try {
            $stripe = new Stripe(config('services.stripe.secret'));

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
     * Creates a stripe customer and saves the stripe_id
     * @return string
     */
    public function createStripeId()
    {
        $stripe = new Stripe(config('services.stripe.secret'));

        $customer = $stripe->customers()->create([
            'email' => $this->email,
        ]);

        $this->stripe_id = $customer['id'];
        $this->save();

        return $customer['id'];
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
            $stripe = new Stripe(config('services.stripe.secret'));

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
        $stripe = new Stripe(config('services.stripe.secret'));

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
     * Gets the users cards
     * @return array
     */
    public function getCards()
    {
        if (!$this->stripe_id) {
            return [];
        }

        $stripe = new Stripe(config('services.stripe.secret'));

        $cards = $stripe->cards()
            ->all($this->stripe_id);

        if (empty($cards['data'])) {
            return [];
        }

        $results = [];
        foreach ($cards['data'] as $card) {
            $results[] = $card;
        }

        return $results;
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

        return $this->createStripeId();
    }
}