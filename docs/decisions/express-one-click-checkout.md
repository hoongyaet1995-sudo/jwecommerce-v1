# Express 1-Click Checkout

**Status:** Deferred beyond P0  
**Decision date:** 2026-08-25

## Decision

Do not display or implement an Express 1-Click Checkout control in the P0 storefront checkout.

The current checkout must remain the operational source of truth: customer contact and shipping details, live delivery and tax calculation, and a configured hosted/offline payment method.

## Why

The Google AI Studio source card describes a simulated local gateway and does not represent a payment-token, wallet, address, fraud, or idempotency implementation. Displaying it before those capabilities exist would make a non-functional payment promise.

Current hosted Stripe, PayPal and Billplz returns also deliberately rely on verified webhooks—not browser return URLs—to confirm payment. An express flow must keep that same payment-confirmation boundary.

## P1: Address acceleration

Consider saved-address selection and secure prefill only after account addresses have an accepted operational QA path. The customer must still review delivery, tax, total, and payment choice before placing the order.

## P2: Provider-backed express payment

Evaluate an express control only when all conditions hold:

1. A configured provider supplies a real customer wallet or token capability, such as Stripe Link, Apple Pay, Google Pay, or PayPal Fastlane.
2. The provider reports the capability as available for the shopper, currency, device, and checkout context.
3. A verified shipping address, delivery rate, tax total, stock reservation, and idempotent order/payment attempt can be created before redirect or authorization.
4. Payment confirmation still occurs only through a verified provider webhook.
5. The express control is hidden automatically when any condition is not met.

Do not add a generic admin toggle that can expose the control without provider-backed capability.

## Planning trigger

Before any work involving P1/P2 checkout acceleration, saved payment methods, wallets, Stripe Link, Apple Pay, Google Pay, PayPal Fastlane, or one-click checkout, read this decision first and include its constraints in the implementation plan.
