# E-Commerce Template

This is a Drupal Commerce e-shop template.

## Included Modules

- Drupal Commerce (products, orders, cart)
- Commerce Stripe (payment gateway)
- Commerce PayPal (payment gateway)
- Simple Sitemap (SEO)

## Configuration

### Products
- Product types are configured in `config/sync/commerce_product.*.yml`
- Add your products via `/admin/commerce/products`

### Payments
- Configure Stripe: `/admin/commerce/config/payment-gateway`
- Configure PayPal: `/admin/commerce/config/payment-gateway`

### Shipping
- Configure shipping methods: `/admin/commerce/config/shipping-method`

## Quick Setup

1. Import configuration: `drush config:import -y`
2. Create your first product type
3. Configure payment gateways
4. Add products and test checkout
