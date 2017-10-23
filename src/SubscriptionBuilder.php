<?php

namespace Coderity\Wallet;

use Laravel\Cashier\SubscriptionBuilder as CashierSubscription;

class SubscriptionBuilder extends CashierSubscription
{
    /**
     * Updates the card which will be used
     *
     * @var string The cardId
     */
    protected $useCard = false;

    /**
     * Create a new Stripe subscription.
     *
     * @param  string|null  $token
     * @param  array  $options
     * @return \Laravel\Cashier\Subscription
     */
    public function create($token = null, array $options = [])
    {
        $customer = $this->getStripeCustomer($token, $options);

        if ($this->useCard) {
            $this->owner->updateDefaultCard($this->useCard);
        }

        $subscription = $customer->subscriptions->create($this->buildPayload());

        if ($this->skipTrial) {
            $trialEndsAt = null;
        } else {
            $trialEndsAt = $this->trialExpires;
        }

        return $this->owner->subscriptions()->create([
            'name' => $this->name,
            'stripe_id' => $subscription->id,
            'stripe_plan' => $this->plan,
            'quantity' => $this->quantity,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => null,
        ]);
    }

    public function useCard($cardId)
    {
        $this->useCard = $cardId;

        return $this;
    }
}
