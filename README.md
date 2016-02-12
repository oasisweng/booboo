#Booboo 
##the fanciest online auction system

### Before start
#####Composer
Install [Composer](http://www.abeautifulsite.net/installing-composer-on-os-x/) for Mac OSX

Install [Composer](https://getcomposer.org/download/) for Windows

#####Bower
Install [Bower](http://bower.io/)

#####MySQL

1. mysqladmin -u root -p password
2. SET GLOBAL event_scheduler = ON;
3. Enter the following code:

```
DELIMITER $$

CREATE 
  EVENT `end_auctions` 
  ON SCHEDULE EVERY 5 MINUTE
  DO BEGIN
  
    -- calculate winner and end auction
    UPDATE
      auction,
      (
      SELECT
        bid.buyerID AS BuyerID,
        bid.bidValue AS BidValue,
        auction.id AS AuctionID
      FROM
        auction
      INNER JOIN
        bid ON bid.auctionID = auction.id
      WHERE
        auction.ended = 0 AND auction.endAt < NOW() AND bid.bidValue =(
        SELECT
          MAX(bid.bidValue)
        FROM
          bid
        WHERE
          bid.createdAt =(
          SELECT
            MIN(bid.createdAt)
          FROM
            bid
          WHERE
            bid.auctionID = auction.id
        )
      )
      ORDER BY
        bid.createdAt ASC
      ) src
      SET
        auction.ended = 1,
        auction.winnerID = src.BuyerID
      WHERE
        auction.id = src.AuctionID
      
  END */$$

DELIMITER ;
```

4. to drop the event, run

```
DROP EVENT `end_auctions`
```

**Start the server:** `php app/console server:start`

### Route
**(Look for relevant controller for more info)**
               
PURPOSE | NAME | PATH | HREF
:------------- | :------------- | :------------- | :------------
Login | user_log | /login | `{{ path('user_login'}}`
Registration | user_registration  | /register | `{{ path('user_registration'}}`
Show user profile| user_show  | /user/{userID} | `{{ path('user_show', {'userID': 1}) }}`
Update user profile | user_update_profile  | /user/{userID}/update_profile | `{{ path('user_update_profile', {'userID': 1}) }}`
Change user password | user_change_password | /user/{userID}/change_password | `{{ path('user_change_password', {'userID': 1}) }}`
Create an auction| auction_new   | /user/{userID}/auction/new | `{{ path('auction_new', {'userID': 1}) }}`
Show an auction| auction_show  | /auction/{auctionID} | `{{ path('auction_show', {'auctionID': 1}) }}`
Edit an auction| auction_edit  | /auction/{auctionID}/edit | `{{ path('auction_edit', {'auctionID': 1}) }}`
Show details of an item | item_show  | /item/{itemId} | `{{ path('item_show', {'itemId': 1}) }}`  
Place a bid | bid_bid | /auction/{auctionID}/bid | `{{ path('bid_bid', {'auctionID': 1}) }}`

### Assets(Images,JS,CSS)
>Javascript and CSS files are combined in a unified file. Please refer to base.html.twig to see the update(within javascript and css block respectively).

TYPE | HREF
------------ | ------------- 
Static images | `{{ asset('assets/images/')}}`
Item Photos | `{{asset('uploads/photos/') ~ item.imageURL}}`


