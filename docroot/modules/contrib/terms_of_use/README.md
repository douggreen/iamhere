# CONTENTS OF THIS FILE

 * Introduction
 * Requirements
 * Installation
 * Configuration


## INTRODUCTION

This module adds a Terms of Use text from a node and a [x] I agree check box to
the registration page.

 * For a full description of the module, visit the project page:
   <https://drupal.org/project/terms_of_use>

 * To submit bug reports and feature suggestions, or to track changes:
   <https://drupal.org/project/issues/terms_of_use>


## REQUIREMENTS

This module requires no modules outside of Drupal core


## INSTALLATION

Install as you would normally install a contributed Drupal module. Visit:
<https://www.drupal.org/node/1897420> for further information.


## CONFIGURATION

    1. Navigate to Administration > Extend and enable the module.
    2. Create a Terms of Use page at node/add/page. Do not promote the node.
    3. Navigate to Administration > Configuration > People > Terms of Use and 
       type the title of your Terms node in the autocomplete text field 
       "Title of the post where your Terms of Use are published".
    4. Save your module configuration.
    5. Clear your Drupal cache.
    6. Log out and access the registration page at user/register.
       It will now be required for anyone wishing to sign up to check the 'I 
       agree with these terms.' checkbox.

