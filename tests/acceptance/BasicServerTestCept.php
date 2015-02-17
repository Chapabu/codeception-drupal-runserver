<?php 
$I = new AcceptanceTester($scenario);
$I->wantTo('see if the webserver is running');
$I->amOnPage('/');
$I->seeResponseCodeIs(200);
