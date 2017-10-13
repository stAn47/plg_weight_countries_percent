# 'Percentage on price' Shipping Plugin for Virtuemart/Joomla
This shipping plugin is a modification of the original plugin of weights and countries of VM, but that allows to enter a percentage on the total price of the purchase of a fixed total. This way will be able to establish some costs in function of the final value of the cart.

[Official site and package download](https://www.joomlaempresa.es/en/downloads/free-extensions.html)


Modified by stAn, [RuposTel.com](https://www.rupostel.com)

Changes compared to the original joomlaempressa.es version: 
- compared against core weight_countries of VM3.2.5
- added category filter per the core plugin
- added new tax hangling for the calculation: before tax / after tax / product subtotal before tax / product subtotal after tax
- added it's own template for display in the cart in /plugins/vmshipment/weight_countries_percent/tmpl/default_cart.php which can be used in your template overrides as /templates/YOUR TEMPLATE/html/weight_countries_percent/default_cart.php
- better handling of the order data fetching from the database

stAn

