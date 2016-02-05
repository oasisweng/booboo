#Booboo 
##the fanciest online auction system
======

### Before start
Install [Composer](http://www.abeautifulsite.net/installing-composer-on-os-x/) for Mac OSX

Install [Composer](https://getcomposer.org/download/) for Windows

Install [Bower](http://bower.io/)

**Start the server:** `php app/console server:start`

### Route
**(Look for relevant controller for more info)**
               
PURPOSE | NAME | PATH | HREF
:------------- | :------------- | :------------- | :------------
Registration | user_registration  | /register | `{{ path('user_registration'}}`
Show user profile| user_show  | /user/{userId} | `{{ path('user_show', {'userId': 1}) }}`
Edit user profile | user_edit  | /user/{userId}/edit | `{{ path('user_edit', {'userId': 1}) }}`
Create an auction| auction_new   | /user/{userId}/auction/new | `{{ path('auction_new', {'userId': 1}) }}`
Show an auction| auction_show  | /auction/{auctionId} | `{{ path('auction_show', {'auctionId': 1}) }}`
Edit an auction| auction_edit  | /auction/{auctionId}/edit | `{{ path('auction_edit', {'auctionId': 1}) }}`
Show details of an item | item_show  | /item/{itemId} | `{{ path('item_show', {'itemId': 1}) }}   `  

### Assets(Images,JS,CSS)
>Javascript and CSS files are combined in a unified file. Please refer to base.html.twig to see the update(within javascript and css block respectively).

TYPE | HREF
------------ | ------------- 
Static images | `{{ asset('assets/images/')}}`
Item Photos | `{{asset('uploads/photos/') ~ item.imageURL}}`


