<?php 
$I = new AcceptanceTester($scenario);
$I->wantTo('see if the webserver is running');
$I->amOnPage('/index.php?q=user');
$I->seeResponseCodeIs(200);
