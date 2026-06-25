# Stripe Payment Gateway Setup

This project now includes Stripe payment integration using a pre-configured Stripe Checkout link for payment simulation.

## Setup Instructions

1. **Stripe Test Link**: The checkout uses the provided test link: https://buy.stripe.com/test_3cIeVcarH6xk3p795XeIw00

2. **Configure Redirect URLs** (Important!):
   - Go to your Stripe Dashboard
   - Find the Checkout session for this link
   - Set **Success URL** to: `http://localhost/FYP-CommerceGo/customer/success.php`
   - Set **Cancel URL** to: `http://localhost/FYP-CommerceGo/customer/cart.php`

3. **Test the Integration**
   - Add items to cart
   - Go to checkout
   - Click "Pay with Stripe" → redirects to your Stripe test link
   - Complete payment with test card
   - Should redirect back to success page with order confirmation

## Features

- **Secure Payments**: Uses Stripe's hosted checkout page
- **Order Management**: Creates orders and order items in database after payment
- **Stock Updates**: Automatically reduces product stock after payment
- **Cart Clearing**: Removes items from cart after successful payment
- **Error Handling**: Shows errors if payment fails

## Database Tables

- `orders`: Stores order information
- `order_items`: Stores individual order line items

## Testing

Use Stripe's test mode with the following:
- Test card: 4242 4242 4242 4242
- Any future expiry date
- Any CVC
- Any name/address

The payment will succeed and redirect to the success page.