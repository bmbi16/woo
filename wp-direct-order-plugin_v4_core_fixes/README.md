# WP Direct Order for WooCommerce Plugin - Instructions

## Description

This plugin allows customers to place Cash on Delivery orders directly from the WooCommerce product page by filling in custom fields (Full Name, Phone Number, Wilaya, Commune) and selecting a shipping method. It bypasses the standard cart and checkout process for a quicker ordering experience.

The plugin is designed with Arabic language support for frontend fields and labels.

## Features

*   Adds custom fields (Full Name, Phone Number, Wilaya, Commune) to the single product page.
*   Dynamic Commune dropdown based on selected Wilaya (using provided Algerian Wilaya/Commune data).
*   Hides the default "Add to Cart" button.
*   Adds an "Order Now" (اطلب الآن) button.
*   Allows selection of available WooCommerce shipping methods.
*   Creates a WooCommerce order automatically upon submission.
*   Sets the order payment method to "Cash on Delivery".
*   Saves custom field data to the order meta.
*   Displays custom field data in the WooCommerce order details (admin, customer view, emails).
*   Admin settings page (WooCommerce -> Direct Order) to:
    *   Enable/Disable the plugin.
    *   Enable/Disable individual custom fields (Full Name, Phone Number, Wilaya, Commune).
    *   Set individual custom fields as required or optional.
*   Responsive design for the custom fields form.
*   Frontend labels and fields primarily in Arabic.

## Installation

1.  Download the `wp-direct-order-plugin.zip` file.
2.  Log in to your WordPress admin dashboard.
3.  Navigate to **Plugins -> Add New**.
4.  Click the **Upload Plugin** button at the top.
5.  Click **Choose File** and select the `wp-direct-order-plugin.zip` file you downloaded.
6.  Click **Install Now**.
7.  Once installed, click **Activate Plugin**.

## Configuration

1.  After activation, navigate to **WooCommerce -> Direct Order** in your WordPress admin menu.
2.  **General Settings:**
    *   **Enable Direct Order:** Check this box to activate the plugin's functionality on product pages. Uncheck to disable it.
3.  **Custom Field Management:**
    *   For each field (Full Name, Phone Number, Wilaya, Commune), you can choose to:
        *   **Enable Field:** Check the box to display this field on the product page form.
        *   **Field Required:** Check the box to make this field mandatory for the customer to fill out. (Note: A field must be enabled to be set as required).
4.  Click **Save Settings**.

## Usage

*   Once enabled and configured, the custom order form will automatically appear on single product pages (replacing the standard Add to Cart button).
*   Customers fill in their details (Name, Phone, Wilaya, Commune), select a shipping method, and click "Order Now".
*   An order will be created in WooCommerce with the status "On Hold" (or your default COD status) and the payment method set to Cash on Delivery.
*   The custom information provided by the customer will be visible in the order details in the WooCommerce admin area, on the customer's order confirmation page, and in order notification emails.

## Shipping Methods

*   The plugin displays shipping methods enabled in your WooCommerce settings (**WooCommerce -> Settings -> Shipping**).
*   Ensure you have configured shipping zones and methods appropriately for Algeria (or your target regions) for the selection to work correctly.
*   Compatibility with specific third-party shipping plugins may vary. Basic WooCommerce shipping methods are supported.

## Translation

*   The plugin uses the text domain `wp-direct-order-plugin`.
*   Frontend fields and labels are primarily hardcoded in Arabic as requested.
*   Admin settings and some backend strings use WordPress internationalization functions.
*   A `.pot` file (translation template) is included in the `/languages` directory. You can use tools like Poedit to create `.po` and `.mo` files for other languages if needed (e.g., `wp-direct-order-plugin-fr_FR.po`).
    *   *Note: Automatic generation of the .pot file failed in the development environment due to missing dependencies. The included .pot file is empty and serves as a placeholder. You may need to regenerate it using WP-CLI (`wp i18n make-pot . languages/wp-direct-order-plugin.pot`) or another tool in your own environment if you need full translation capabilities.*

## Data

The Algerian Wilaya and Commune data is stored in `/data/algeria_cities.json` within the plugin directory.

## Important Notes

*   This plugin creates orders directly. Ensure your Cash on Delivery process is well-defined.
*   The plugin assumes a quantity of 1 for the ordered product.
*   Thoroughly test the plugin with your theme and other active plugins to ensure compatibility, especially regarding shipping methods.

