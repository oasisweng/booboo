Booboo, the fanciest online auction system
======

### Before start
Install [Composer](http://www.abeautifulsite.net/installing-composer-on-os-x/) for Mac OSX

Install [Composer](https://getcomposer.org/download/) for Windows

Install [Bower](http://bower.io/)

**Start the server:**
```sh
php app/console server:start
```
### Route
**(Look for relevant controller for more info)**
               
PURPOSE | NAME | PATH
:------------- | :------------- | :-------------
Registration | user_registration  | /register
Show user profile| user_show  | /user/{userId}     
Edit user profile | user_edit  | /user/{userId}/edit
Create an auction| auction_new   | /user/{userId}/auction/new
Show an auction| auction_show  | /auction/{auctionId}  
Edit an auction| auction_edit  | /auction/{auctionId}/edit 
Show details of an item | item_show  | /item/{itemId}      
