<?php

/*
Plugin Name: PeoplePond
Plugin URI: http://wordpress.org/extend/plugins/PeoplePond/
Description: <a href="http://www.peoplepond.com" title="PeoplePond">PeoplePond</a> provides the tools needed to take ownership of your online identity and reputation management. The plugin retrieves your About Me profile from PeoplePond, and displays it in your About page on your blog. To setup, please go to Settings -&gt; PeoplePond.
Version: 1.1.9
Author: Neil Simon
Author URI: http://peoplepond.com/
*/


/*
 Copyright 2009 PeoplePond.

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin St, 5th Floor, Boston, MA 02110 USA
*/


// Constants
define ('PEOPLEPOND_PLUGIN',         'PeoplePond WordPress Plugin');
define ('PEOPLEPOND_PLUGIN_VERSION', 'PeoplePond-v1.1.9');
define ('PEOPLEPOND_OPTIONS',        'peoplepondOptions');
define ('PEOPLEPOND_API_URL',        'http://adam.peoplepond.com/peeps.php');
define ('PEOPLEPOND_REGISTER_URL',   'http://www.peoplepond.com/register.php');
define ('PEOPLEPOND_CONTACT_US_URL', 'http://www.peoplepond.com/contact_us.php');


// Function return codes
define ('PEOPLEPOND_RC_OK',                     0);
define ('PEOPLEPOND_RC_EMAILADDRESS_NOT_FOUND', 1);
define ('PEOPLEPOND_RC_EMPTY_STRING',           2);
define ('PEOPLEPOND_RC_ADD_ABOUT_PAGE_FAILED',  3);


function peoplepond_cURL ($md5_emailAddressIn)
    {
    // Ex: http://adam.peoplepond.com/peeps.php?email=4240be8e2dc90b4aef080848af60435f
    $curlUrl = sprintf ("%s?email=%s", PEOPLEPOND_API_URL, $md5_emailAddressIn);

    // Load existing options from WordPress database
    $peoplepondOptions = get_option (PEOPLEPOND_OPTIONS);

    // Append ADAM parameters
    if ($peoplepondOptions ['bio']       == TRUE)   $curlUrl .= '&bio=yes';
    if ($peoplepondOptions ['image']     == TRUE)   $curlUrl .= '&image=yes';
    if ($peoplepondOptions ['imageWrap'] == TRUE)   $curlUrl .= '&embed=yes';
    if ($peoplepondOptions ['social']    == TRUE)   $curlUrl .= '&social=yes';

    // Append site= parameter
    $curlUrl .= ('&site=' . $_SERVER ['SERVER_NAME']);

    // Create a new cURL resource
    $ch = curl_init ();

    // Set cURL options
    curl_setopt ($ch, CURLOPT_POST,           1);
    curl_setopt ($ch, CURLOPT_POSTFIELDS,     '');
    curl_setopt ($ch, CURLOPT_URL,            $curlUrl);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, TRUE);

    // Prepend @ to this call to suppress cURL warning in some environments
    @curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, TRUE);

    // Exec the URL
    $response = curl_exec ($ch);

    // Close cURL resource
    curl_close ($ch);

    return ($response);
    }


function peoplepond_createAboutPage ($pageNameIn, $commentSettingsIn)
    {
    // Load existing options from WordPress database
    $peoplepondOptions = get_option (PEOPLEPOND_OPTIONS);

    // Call the ADAM API
    if (($peepsResponse = peoplepond_cURL (md5 ($peoplepondOptions ['emailAddress']))) == FALSE)
        {
        // This happens when:
        // 1) The site is not found (site could be down)
        // 2) The site is found, but the emailAddress hash does not resolve

        $rc = PEOPLEPOND_RC_EMAILADDRESS_NOT_FOUND;
        }

    elseif (empty ($peepsResponse))
        {
        $rc = PEOPLEPOND_RC_EMPTY_STRING;
        }

    // Post the about page with the new API data
    elseif (peoplepond_addAboutPage ($pageNameIn, $commentSettingsIn, $peepsResponse) == 0)
        {
        $rc = PEOPLEPOND_RC_OK;
        }

    else
        {
        $rc = PEOPLEPOND_RC_ADD_ABOUT_PAGE_FAILED;
        }

    return ($rc);
    }


function peoplepond_addAboutPage ($pageNameIn, $commentSettingsIn, $peepsResponse)
    {
    $rc = 1;  // reset to 0 upon success

    // Declare the array to hold the post values
    $postArray = array ();

    // All available "wp_post" database fields are listed below
    // They are not required -- WordPress uses appropriate default values

    //$postArray ['ID']                    = ;
    //$postArray ['post_date']             = ;
    //$postArray ['post_date_gmt']         = ;
    //$postArray ['ping_status']           = ;
    //$postArray ['post_password']         = ;
    //$postArray ['post_name']             = ;
    //$postArray ['to_ping']               = ;
    //$postArray ['pinged']                = ;
    //$postArray ['post_modified']         = ;
    //$postArray ['post_modified_gmt']     = ;
    //$postArray ['post_content_filtered'] = ;
    //$postArray ['post_parent']           = ;
    //$postArray ['guid']                  = ;
    //$postArray ['menu_order']            = ;
    //$postArray ['post_mime_type']        = ;
    //$postArray ['comment_count']         = ;

    // Setup the wp_insert_post parameters
    $postArray ['post_author']    = null;
    $postArray ['post_content']   = $peepsResponse;
    $postArray ['post_title']     = $pageNameIn;
    $postArray ['post_category']  = array (0);
    $postArray ['post_status']    = 'publish';
    $postArray ['comment_status'] = $commentSettingsIn;
    $postArray ['post_type']      = 'page';

    if (wp_insert_post ($postArray) != 0)
        {
        // Post was successful
        $rc = 0;
        }

    return ($rc);
    }


function peoplepond_updateOptions ()
    {
    // Load existing options
    $peoplepondOptions = get_option (PEOPLEPOND_OPTIONS);

    // Localize displayed strings
    $signupStr            = __('If you do not have a PeoplePond account, you can signup here: ', 'peoplepond');
    $sendSuggestionsStr   = __('Please send us feedback and suggestions',                        'peoplepond');
    $peoplepondProfileStr = __('PeoplePond Profile',                                             'peoplepond');
    $emailStr             = __('PeoplePond E-mail Account:',                                     'peoplepond');
    $selectAboutStr       = __('Please Select an About Page:',                                   'peoplepond');
    $chooseAboutStr       = __('ERROR: Please Choose an About Page From the Drop-Down Box.',     'peoplepond');
    $emailRequiredStr     = __('ERROR: Please Enter Your PeoplePond E-mail Address.',            'peoplepond');
    $saveStr              = __('Save',                                                           'peoplepond');
    $aboutStr             = __('About',                                                          'peoplepond');
    $bioStr               = __('Display PeoplePond Bio',                                         'peoplepond');
    $imageStr             = __('Display PeoplePond Image',                                       'peoplepond');
    $imageWrapStr         = __('Display PeoplePond Image Wrapped Inside Text',                   'peoplepond');
    $socialStr            = __('Display Links To Your Other Social Websites',                    'peoplepond');

    // If ALL data fields contain values...
    if (isset ($_POST ['emailAddress']) && (isset ($_POST ['chosenAbout'])))
        {
        // Get the About page name
        $newPageName = '';
        if (peoplepond_getAboutPageName ($_POST ['chosenAbout'], $newPageName) != 0)
            {
            // Error -- About page name not chosen from drop-down
            echo '<div id="message" class="updated fade"><p>' . $chooseAboutStr . '</p></div>';
            }

        // If (emailAddress is blank)
        else if (empty ($_POST ['emailAddress']))
            {
            // Error -- Email address required
            echo '<div id="message" class="updated fade"><p>' . $emailRequiredStr . '</p></div>';
            }

        else
            {
            // Copy data to the WordPress database fields
            $peoplepondOptions ['emailAddress'] = strtolower (trim ($_POST ['emailAddress']));
            $peoplepondOptions ['bio']          = ($_POST ['bio']       == "TRUE") ? TRUE : FALSE;
            $peoplepondOptions ['image']        = ($_POST ['image']     == "TRUE") ? TRUE : FALSE;
            $peoplepondOptions ['imageWrap']    = ($_POST ['imageWrap'] == "TRUE") ? TRUE : FALSE;
            $peoplepondOptions ['social']       = ($_POST ['social']    == "TRUE") ? TRUE : FALSE;

            // Store changed options back to WordPress database
            update_option (PEOPLEPOND_OPTIONS, $peoplepondOptions);

            // Save and deactivate old page (if applicable), then create new
            processAboutPage ($newPageName);
            }
        }

    // Set the state of the Display PeoplePond ??? checkboxes
    $bioCbState       = ($peoplepondOptions ['bio']       == TRUE) ? 'checked' : '';
    $imageCbState     = ($peoplepondOptions ['image']     == TRUE) ? 'checked' : '';
    $imageWrapCbState = ($peoplepondOptions ['imageWrap'] == TRUE) ? 'checked' : '';
    $socialCbState    = ($peoplepondOptions ['social']    == TRUE) ? 'checked' : '';

    // Display the options page
    echo
     '<div class="wrap">

      <br /><a href="http://www.peoplepond.com"><img src="' . trailingslashit (get_option ('siteurl')) . PLUGINDIR . '/peoplepond/logo.jpg" /></a>

      <h3>' . $peoplepondProfileStr . '</h3>

      ' . $signupStr          . '<a href="' . PEOPLEPOND_REGISTER_URL   . '">' . PEOPLEPOND_REGISTER_URL   . '</a>
      <br /><br />

      <a href="' . PEOPLEPOND_CONTACT_US_URL . '">' . $sendSuggestionsStr . '</a>
      <br /><br />

      <hr /><br />

      <form action="" method="post">

      ' . $emailStr . '<br />
      <input type="text" name="emailAddress" value="' . $peoplepondOptions ['emailAddress'] . '" size="40" /><br /><br /><br />

      ' . $selectAboutStr . '<br />
      <select name="chosenAbout">
      <option>Choose One</option>';

      // Get all page names, populate drop-down list
      $aboutFound = FALSE;
      $pages = get_pages ();
      foreach ($pages as $singlePage)
          {
          // If 'about' page is NOT found, create entry for it below
          if (strcasecmp ($singlePage->post_title, $aboutStr) == 0)
              {
              $aboutFound = TRUE;
              }
          echo '<option value="' . $singlePage->post_title . '">' . $singlePage->post_title . '</option>';
          }
      if ($aboutFound == FALSE)
          {
          echo '<option value="' . $aboutStr . '">' . $aboutStr . '</option>';
          }

    echo
      '</select><br /><br /><br />

      <input type="checkbox" name="bio"       value="TRUE"' .$bioCbState       .'>' . ' ' .$bioStr       .'<br /><br />
      <input type="checkbox" name="image"     value="TRUE"' .$imageCbState     .'>' . ' ' .$imageStr     .'<br /><br />
      <input type="checkbox" name="imageWrap" value="TRUE"' .$imageWrapCbState .'>' . ' ' .$imageWrapStr .'<br /><br />
      <input type="checkbox" name="social"    value="TRUE"' .$socialCbState    .'>' . ' ' .$socialStr    .'<br /><br />

      <p><input type="submit" value="' . $saveStr . '" /></p>

      </form>

      </div>';
    }


function processAboutPage ($newPageNameIn)
    {
    // Localize displayed strings
    $emptyStringStr   = __('ERROR: We could not find your PeoplePond account or your account is inactive. ' .
                           'Please register or login and correct any errors to activate your account.',
                                                                          'peoplepond');
    $addFailedStr     = __('ERROR: Unable to add about page.',            'peoplepond');
    $addSuccessfulStr = __('PeoplePond About page created successfully:', 'peoplepond');
    $origSavedStr     = __('Original About page saved as:',               'peoplepond');

    // Initialize state of "did we save an original about page" for status message
    $origSaved     = FALSE;
    $savedPageName = '';

    // Default comment status to open
    $commentSettings = 'open';

    // Try to retrieve existing about page (if one exists)
    $existingAboutPage = (array) get_page_by_title ($newPageNameIn);

    // If chosen about-page exists...
    if (!empty ($existingAboutPage))
        {
        // Update to 'draft'
        $existingAboutPage ['post_status'] = 'draft';

        // Rename page (append '-saved-Y-m-d-H-i-s'), where:
        // Y ... 4 digit year
        // m ... 2 digit month   (with leading zeros)
        // d ... 2 digit day     (with leading zeros)
        // H ... 2 digit hour    (24 hour format with leading zeros)
        // i ... 2 digit minutes (with leading zeros)
        // s ... 2 digit seconds (with leading zeros)
        $existingAboutPage ['post_title'] .= sprintf ('-saved-%s', date ('Y-m-d-H-i-s'));

        // Update old about page to be a 'renamed draft'
        wp_update_post ($existingAboutPage);

        // For status message
        $origSaved = TRUE;
        }

    echo '<div id="message" class="updated fade"><p>';

    // Create the about page -- pass the commentSettings from original about page
    switch (peoplepond_createAboutPage ($newPageNameIn, $commentSettings))
        {
        case PEOPLEPOND_RC_EMAILADDRESS_NOT_FOUND:
        case PEOPLEPOND_RC_EMPTY_STRING:
            {
            echo $emptyStringStr;
            break;
            }

        case PEOPLEPOND_RC_ADD_ABOUT_PAGE_FAILED:
            {
            echo $addFailedStr;
            break;
            }

        case PEOPLEPOND_RC_OK:
            {
            echo $addSuccessfulStr . ' ' . '<b>' . $newPageNameIn . '</b>';

            if ($origSaved == TRUE)
                {
                // Display "Original about page saved as: xxx"
                echo '<br /><br />' . $origSavedStr . ' ' . '<b>' . $existingAboutPage ['post_title'] . '</b>';
                }

            break;
            }
        }

    echo '</p></div>';
    }


function peoplepond_getAboutPageName ($chosenAboutIn, &$pageNameOut)
    {
    $rc = 1;  // reset to 0 upon success

    if (strcmp ($chosenAboutIn, 'Choose One') != 0)
        {
        // An existing page was chosen from drop-down -- pass back as output parameter
        $pageNameOut = $chosenAboutIn;

        // Successful
        $rc = 0;
        }

    return ($rc);
    }


function peoplepond_the_content ($contentIn)
    {
    // If it's a PeoplePond About Page, we'll reassign this
    $contentToDisplay = $contentIn;

    // If this is a PeoplePond About Page...
    if ((strstr ($contentIn, "http://peoplepond.com"))   &&
        (strstr ($contentIn, "PeoplePond Online Ident")) &&
        (strstr ($contentIn, "Powered by PeoplePond")))
        {
        // Load existing options from WordPress database
        $peoplepondOptions = get_option (PEOPLEPOND_OPTIONS);

        // Call the ADAM API
        if (($peepsResponse = peoplepond_cURL (md5 ($peoplepondOptions ['emailAddress']))) == FALSE)
            {
            // This happens when:
            // 1) The site is not found (site could be down)
            // 2) The site is found, but the emailAddress hash does not resolve

            // just exit - don't update the post
            }

        elseif (empty ($peepsResponse))
            {
            // just exit - don't update the post
            }

        // Update the about page with the new ADAM data
        else
            {
            // Expose the specific post for updating
            global $post;

            // Copy the ADAM data to the post_content
            $post->post_content = $peepsResponse;

            // Update post with refreshed ADAM content
            wp_update_post ($post);

            // For display, surround the new content with plugin-version div tag
            $contentToDisplay = sprintf ('<div id="%s">%s</div>', PEOPLEPOND_PLUGIN_VERSION, $peepsResponse);
            }
        }

    // Display the page
    return ($contentToDisplay);
    }


function peoplepond_addSubmenu ()
    {
    // Define the options for the submenu page
    add_submenu_page ('options-general.php',       // Parent page
                      'PeoplePond page',           // Page title, shown in titlebar
                      'PeoplePond',                // Menu title
                      10,                          // Access level all
                      __FILE__,                    // This file displays the options page
                      'peoplepond_updateOptions'); // Function that displays options page
    }


function peoplepond_createOptions ()
    {
    // Get the logged-in user's email address to use as default on options page
    global $current_user;
    get_currentuserinfo ();

    // Create the initialOptions array of keys/values
    $peoplepond_initialOptions = array ('emailAddress' => strtolower (trim ($current_user->user_email)),
                                        'bio'          => TRUE,
                                        'image'        => TRUE,
                                        'imageWrap'    => TRUE,
                                        'social'       => TRUE);

    // Store the initialOptions to the WordPress database
    add_option (PEOPLEPOND_OPTIONS, $peoplepond_initialOptions);
    }


function peoplepond_deleteOptions ()
    {
    // Remove the peoplepondOptions array from the WordPress database
    delete_option (PEOPLEPOND_OPTIONS);
    }


// Initialize for localized strings
load_plugin_textdomain ('peoplepond', 'wp-content/plugins/peoplepond');


// Runs once at activation time
register_activation_hook (__FILE__, 'peoplepond_createOptions');


// Runs once at deactivation time
register_deactivation_hook (__FILE__, 'peoplepond_deleteOptions');


// Add action hook for submenu to the options page
add_action ('admin_menu', 'peoplepond_addSubmenu');


// Add filter hook so we can check for "About Page" load -- to do refreshes
add_filter ('the_content', 'peoplepond_the_content');

?>
