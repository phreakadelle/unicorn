# unicorn

A Dropbox gallery for your website.
More information can be obtained here:
* https://github.com/unicorn-gallery/unicorn

Follow those instructions to setup the galery.


# Digital Photoframe
I've forked the unicorn repository in order to create a digital photoframe / digitaler bilderrahmen (de). My requirement was to be able to send photos by email and get them imported into the galery. The story behind that is that i would like to give my family the chance to also send pictures by mail to the galery. The Galery is displayed on an old Tablet / iPad which i would like to give to my grandparents as a christmas present.

I havent found any App in the AppStore that could provide such a functionality. Also i wanted to be able to store the data in my own cloud.

# Advantages of the Solution
* You can update the pictures using Dropbox. 
* Also your family can send pictures by email to a dedicated adress. 
* No reason to update the images on the iPad. The galery is auto refreshing so my grandparents do not need to press any button.

# Technical Requirements
Its quiet easy if you already have a Website where you can run the gallery and create mail accounts

## Email Account
Dedicated Mail Account to retrieve the Images. 

# Cronjob
The "mail2dropbox.php" should be executed via Cronjob to import the mails on a frequent basis
