See English readme [below](#1-system-requirements).

1. Требования к CMS
-------------------

* Версия Joomla 2.5/3.x
* Virtuemart 2.6/3.x
* JoomShopping 3.x/4.x
* HikaShop 2.2+

2. Установите плагин Convead через Менеджер расширений Joomla
-------------------------------------------------------------

* В административной панели вашего магазина Joomla перейдите в «Менеджер расширений», раздел «Установка».
* Перейдите на вкладку «Установить из URL», введите адрес плагина в нашем репозитории: `https://github.com/Convead/joomla_convead/archive/master.zip` и нажмите «Установить».
* После успешной установки вы увидите надпись «Установка плагина успешно завершена».
* Перейдите в раздел «Менеджер расширений» → «Управление». Найдите плагин Convead с помощью строки поиска, отметьте его галкой и нажмите кнопку «Включить».

3. Настройте плагин
-------------------

* Перейдите в раздел «Расширения» → «Менеджер плагинов». Найдите плагин Convead с помощью строки поиска и кликните по его названию.
* Впишите в соответствующее поле API-ключ вашего аккаунта Convead.
* Измените идентификатор валюты вашего магазина в случае необходимости.

> ##### Как узнать идентификатор валюты?

> **VirtueMart**  
> Меню «Компоненты» → «VirtueMart» → «Конфигурация» → «Настройки» → «Валюта». Смотрите код валюты в колонке «Код (3)».

> **JoomShopping**  
> Меню «Компоненты» → «JoomShopping» → «Опции» → «Валюта». Кликните по названию валюты, чтобы увидеть ее код.

> **HikaShop**  
> Меню «Компоненты» → «HikaShop» → «Конфигурация». Раздел «Система» → «Валюта». Смотрите код валюты в поле «Основная денежная единица».

* Убедитесь, что плагины Joomla разрешены в вашем интернет-магазине:

> **VirtueMart**  
> Меню «Компоненты» → «VirtueMart» → «Конфигурация». В разделе «Настройки магазина» должна быть включена опция «Включить плагины Joomla».

> **JoomShopping**  
> Меню «Компоненты» → JoomShopping → «Настройки» → «Товар». Опция «Использовать плагины в описании?» должна быть включена.

> **HikaShop**  
> Ура! Ничего не нужно делать!

* Если у вас магазин на VirtueMart, то вам придется внести одно небольшое изменение в исходный код магазина:

1. Откройте файл `components/com_virtuemart/helpers/cart.php`
2. Найдите функцию `public function add(...) {...}`
3. В конце этой функции **перед** строчкой `if ($updateSession== false) return false;` вставьте следующий код:

```php
/* Convead hack */
JPluginHelper::importPlugin('vmcustom');
$dispatcher = JEventDispatcher::getInstance();
$dispatcher->trigger('plgVmOnAddToCart',array($this));
/* End Convead hack */
```

***

1. System Requirements
----------------------

* Joomla version 2.5/3.x
* Virtuemart 2.6/3.x
* JoomShopping 3.x/4.x
* HikaShop 2.2+

2. Install Convead plugin using Joomla Extension Manager
--------------------------------------------------------

* In admin panel of your Joomla website navigate to "Extensions" → "Extension Manager" menu, section "Install".
* Go to "Install from URL" tab, type in our plugin URL: `https://github.com/Convead/joomla_convead/archive/master.zip` and press "Install".
* After successful installation you will see "Installing plugin was successful" message.
* Navigate to "Extensions" → "Extension Manager" → "Manager" menu. Find Convead plugin using search field, tick a checkbox left to plugin name and press "Enable" button.

3. Setup plugin
---------------

* Navigate to "Extensions" → "Extension Manager" section. Find Convead plugin using search field and click on its name.
* Type in your Convead account API-key into a corresponding field.
* Change your currency code if necessary.

> ##### How to get currency code?

> **VirtueMart**  
> Menu "Components" → "VirtueMart" → "Configuration" → "Currencies". See currency code in "Code (3 letters)" column.

> **JoomShopping**  
> Menu "Components" → "JoomShopping" → "Configuration" → "Currency". Click on currency name to get its code.

> **HikaShop**  
> Menu "Components" → "HikaShop" → "Configuration". Section "System" → "Currency". See currency code in "Main currency" field.

* Make sure that Joomla plugins are enabled in your store:

> **VirtueMart**  
> Menu "Components" → "VirtueMart" → "Configuration". Option "Enable Joomla Plugin" must be enabled within "Shop Settings" section.

> **JoomShopping**  
> Menu "Components" → "JoomShopping" → "Configuration" → "Product". Option "Use content plugins in descriptions?" must be enabled.

> **HikaShop**  
> There is nothing to do! Yay!

* If you run your store under VirtueMart, you have to make some additional changes in store's source code.:

1. Open `components/com_virtuemart/helpers/cart.php` file.
2. Find function `public function add(...) {...}`
3. Insert the following code at the bottom of the function **before** `if ($updateSession== false) return false;` line:

```php
/* Convead hack */
JPluginHelper::importPlugin('vmcustom');
$dispatcher = JEventDispatcher::getInstance();
$dispatcher->trigger('plgVmOnAddToCart',array($this));
/* End Convead hack */
```