Veritrans&nbsp; CiviCRM Payment Gateway Plugin
=====================================

Veritrans&nbsp; :heart: CiviCRM!
Let your CiviCRM store integrated with Veritrans&nbsp; payment gateway.

### Description

Veritrans&nbsp; payment gateway is an online payment gateway. They strive to make payments simple for both the merchant and customers. With this plugin you can allow online payment on your CiviCRM store using Veritrans&nbsp; payment gateway.

Payment Method Feature:
- Veritrans&nbsp; vtweb all payment method fullpayment

### Installation

#### Minimum Requirements

* CiviCRM v4.7 or greater (tested up to v4.7.x)
* PHP version v5.4 or greater
* MySQL version v5.0 or greater

#### Manual Installation

1. [Download](/archive/master.zip) the plugin from this repository.
2. Extract the plugin, then rename the folder modules as **com.veritrans.payment.vtweb**
2. Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your CiviCRM extension directory `/civicrm/ext`. For example, in WordPress is `[Wordpress Directory]/wp-content/uploads/civicrm/ext`. More on: [how to install CiviCRM extension](https://wiki.civicrm.org/confluence/display/CRMDOC/Extensions).
3. Go to CiviCRM admin panel, activate the plugin from menu **Administer > System Settings > Manage Extensions**.
4. Click **Refresh**, wait until Veritrans plugin appear, then Install and Enable the Veritrans extension.
5. On Veritrans plugin, Click **Disable**, click **Disable** on confirmation page, Click **Uninstall**. After you are sure the plugin is unistalled,
6. Go to plugin folder (.../civicrm/ext/com.veritrans.payment.vtweb), edit `info.xml` change on line 2:
`type="payment"` to `type="module"`
7. Click **Refresh**, wait until Veritrans plugin appear, then Install and Enable the Veritrans extension once again.

#### Configuration
1. Go to menu **Administer > System Settings > Payment Processors**, click **Add Payment Processor** :
  * Select **Payment Processor Type** : `Veritrans`
  * Fill **Name** with text button that you want to display to customer
  * Tick/Enable **Is this Payment Processor active?**
  * Fill in the **serverKey** for Live Payments with your corresonding [Veritrans&nbsp; account](https://my.veritrans.co.id/) Server key for Production Mode
  * Fill in the **serverKey** for Test Payments with your corresonding [Veritrans&nbsp; account](https://my.veritrans.co.id/) Server key for Sandbox Mode
  * Note: key for Sandbox & Production is different, make sure you use the correct one.
  * Other configuration are optional, you may configure according to your needs.
  * Click **Save**
2. Now click **Edit** on the newly created Veritrans Payment Processor
3. On this Veritrans Payment Processor page look at the browser URL, will be something like this `../admin.php?page=CiviCRM&q=civicrm%2Fadmin%2FpaymentProcessor&action=update&id=17&reset=1`, take note on the IDnumber on `&id=xx`, this IDnumber will be used for next step.

### Veritrans&nbsp; MAP Configuration

1. Login to your [Veritrans&nbsp; Account](https://my.veritrans.co.id), select your environment (sandbox/production), go to menu **settings > configuration**
  * Insert `http://[your web url to CiviCRM]/?page=CiviCRM&q=civicrm/payment/ipn/[IDnumber]` as your Payment Notification URL. Replace `[IDnumber]` with the IDnumber we got previously. For example: `http://myweb.com/civicrm/?page=CiviCRM&q=civicrm/payment/ipn/17`
  * Insert `http://[your web url to CiviCRM]` link as Finish/Unfinish/Error Redirect URL.

### Get help

* [vtweb-civicrm Wiki](https://github.com/veritrans/vtweb-civicrm)
* [Veritrans registration](https://my.veritrans.co.id/register)
* [Vt Web documentation](http://docs.veritrans.co.id)
* Can't find answer you looking for? email to [support@veritrans.co.id](mailto:support@veritrans.co.id)
