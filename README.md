BigTree CMS 4.0
===============
<http://www.bigtreecms.org/>

Licensing
---------
BigTree CMS is publicly licensed under the [GNU Lesser General Public License](http://www.gnu.org/copyleft/lesser.html).
If you would like to use BigTree under a different license, please [contact us](mailto:info@fastspot.com).

Contributing
------------
We would love to have the community work with us on BigTree.  Guidelines are currently being created for how community contributions will be worked back into the project. For more information, please contact <contribute@bigtreecms.org>.  If you would like to begin developing the BigTree core, follow the process below:

1. Fork it.
2. Create a branch (`git checkout -b 4.0_toms_branch`)
3. Commit your changes (`git commit -am "Fixed My Broken Foot"`)
4. Push to the branch (`git push origin 4.0_toms_branch`)
5. Create an [Issue][1] with a link to your branch

Changelog
---------

### 4.0b7
- NEW: Redesigned sample site that provides more in depth examples of using BigTree
- NEW: Field Types are now able to be used in Settings
- NEW: Gravatar support for users
- NEW: Date Time Picker support
- NEW: BigTree::describeTable method for a faster way to get SQL table columns
- NEW: Foreign key constraints are now recognized when creating a form and are automatically created to be database populated lists.
- NEW: ENUM columns are now recognized when creating a form and are automatically created to be static lists.
- NEW: BigTreeModule::getSitemap method to allow for drawing sitemap branches from a module class.
- UPDATED: LESS Compiler to 0.3.5
- UPDATED: Authentication no longer caches permissions via sessions.
- UPDATED: New installs now set SERVER_ROOT in /site/index.php to allow for sym-linked /core/ folders.
- UPDATED: Install.php can now accept command line options instead of $_POST vars for automated installs.
- UPDATED: New installs will receive indexes and foreign key constraints on bigtree core tables.
- UPDATED: Retina assets for custom controls.
- UPDATED: CSS parsing to include root variable auto replacing (www_root/ admin_root/ static_root/ etc).
- FIXED: Custom select boxes now blur other select boxes when clicked.
- FIXED: Custom select boxes now scroll the window down to show their full drop down when low on the page.
- FIXED: A bug with SEO scoring unique titles improperly.
- FIXED: Turning on notices when debugging a custom module shouldn't break the whole admin now.
- FIXED: Bug related to locked pages/entries.
- FIXED: Searching users, settings, and resources is no longer case sensitive
- FIXED: Missing jump dropdown in Dashboard areas.
- FIXED: Searching auto modules is no longer case sensitive
- FIXED: Missing "custom" fields in view options, field options, other dialogs
- FIXED: Default templates using $content instead of $bigtree["content"]
- FIXED: Google Analytics setup failing to store encrypted information properly in the database.
- FIXED: Dialogs now stay centered on the screen when the browser resizes.
- FIXED: Bug that caused image resources to use {wwwroot} over {staticroot}
- FIXED: Empty module groups are no longer shown in the Modules dropdown
- FIXED: File Browser "Cancel" button not closing the window when packaging a module.
- FIXED: The front end editor now alerts a user if there is no editable content.
- FIXED: Custom selects misbehaving in dialogs
- FIXED: Sorting via fields not using backticks (`) around column names
- FIXED: RSS 2 feeds not really being RSS 2.0
- FIXED: Warning that could show when preprocessing functions didn't return an array
- FIXED: A rare bug where creating a new item in a module before the module's view was cached would make the existing items never cache.
- REMOVED: Custom JavaScript and CSS in Auto Module forms.
- REMOVED: Uncached ability in Auto Module views.

### 4.0b6
- NEW: BigTree now allows for usage of index.php routing WITHOUT .htaccess / mod_rewrite
- NEW: BigTree::unzip function (preparing for the future)
- FIXED: Buttons in the image browser not working in beta 5.
- FIXED: Example site "Wonders" form missing in beta 5.
- FIXED: Module forms not creating properly in beta 5.
- FIXED: Choosing image size not working in Image Browser in beta 5.
- FIXED: Styles of the H3 in the image size chooser in the Image Browser.
- FIXED:  404s in the 404 list not being htmlspecialchar'd
- FIXED: Some "Advanced Link" problems in TinyMCE
- FIXED: Views with more than 5 columns causing a critical error.
- FIXED: Many problems that stopped module packaging / importing from working in recent betas.
- FIXED: Callout images throwing an error if they were unchanged from last publish (Thanks Phil P!)
- FIXED: A warning that occurred if you uploaded an invalid image.
- FIXED: Lazy loading of modules throwing a critical error when class_exists() was called (fixes Module Designer!)
- FIXED: Module creation process showing urlencoded titles
- FIXED: Homepage resources loading into a new page if the template was changed (Thanks Phil P!)

### 4.0b5
- NEW: Array of Items now supports using several different field types (text, textarea, date, time, html)
- NEW: BigTree version updater automatically does database and file system changes when a new revision is installed.
- NEW: "Trunk" flag for pages that allows for resetting BigTreeCMS::getTopLevelNav and BigTreeCMS::getBreadcrumbByPage methods.
- UPDATED: TinyMCE to latest version.
- UPDATED: BigTreeAdmin::updateSetting now supports system settings.
- UPDATED: System settings are now consolidated to not clutter the bigtree_settings table so much.
- UPDATED: Cleaned up list-generating code to be usable by third party field types (see BigTreeListMaker JavaScript class).
- UPDATED: Callouts "Title" now renamed "Label" so there aren't two things called "Title".
- UPDATED: Daily Digest email now sends out emails alerting you of unread messages in Message Center.
- UPDATED: Cleaned up the global namespace to move several variables ($content, $layout, $page, $callouts, $resources) into a $bigtree array variable.
- FIXED: A possible notice in install.php
- FIXED: Updating a pending page change (fixes restoring to a revision when a pending change to a page exists)
- FIXED: "Cron" no longer tries to run Google Analytics if a profile isn't set.
- FIXED: The size of some panels in the Image/File browser.
- FIXED: Many to Many editor's odd style issues.
- FIXED: Generated routes failing when publishing a pending item.
- FIXED: Preview URL for the homepage.
- FIXED: Double-encoding of HTML entities for callouts.
- FIXED: Errors for "Array of Items" when used in callouts.
- FIXED: Some issues with inline popups in TinyMCE.
- FIXED: Custom select boxes were firing "changed" instead of "change" like a normal <select> element would.
- FIXED: Tooltips not hiding properly (and causing things behind them to be unclickable)
- FIXED: Creating a user not setting the daily digest flag properly.
- FIXED: "Cron" not getting the right environment variables when running daily digest.
- FIXED: "Growl" messages not showing up in Users section.
- FIXED: Users rows not disappearing after deleting them.
- FIXED: Deleting a user confirmation dialog saying "Resource" instead of "User"
- FIXED: Styling and clickablility of Quick Search results in admin.
- FIXED: File Browser in IE8, removed it's use with a warning in < IE8.
- FIXED: Sub directories are no longer (attempted to be) included in /custom/inc/required/ (thus throwing a warning)
- FIXED: Pages not publishing certain properties properly when published via the Pending Changes section of Dashboard.
- FIXED: Saving a revision not showing the new revision immediately.
- FIXED: Array of Items not getting a draggable placeholder
- FIXED: Daily digest going out even if there was nothing for the user to be notified about.

### 4.0b4
- Fixed issues with saving pending changes on pages that were empty of content.
- Fixed publishing pending changes for pages from the dashboard
- Fixed the number of pending page changes on the dashboard always showing 1.
- Updated the layout of the user permission editor to list modules by group.
- Fixed the module permissions always showing a blue arrow even when sub-permissions were not available.
- Fixed default date format for the date picker if "Default to Today's Date" was set.
- Fixed callout files/images disappearing on re-save
- Fixed resources in callouts saving strangely.
- Fixed callout resources ignoring validation rules.
- Restored ability to add classes to images in TinyMCE
- Added missing + buttons in module designer.
- Removed confirm dialogs from deleting fields from a form.
- Fixed an error that caused options for a view to not save (and throw a warning) on initial creation.
- Fixed cron-job not running properly.
- Fixed BigTreeCMS::makeSecure
- Fixed a few Javascript events in the admin (changing callout types and a few other places were broken in beta 3)
- Switched sorting in the admin to use POST instead of GET (to support thousands of items).

### 4.0b3
- Updated image cropper count design to make the number of crops more obvious
- Updated callouts to allow developers to set a default title.
- Updated module creation so that if there isn't a related table it throws a growl and moves away from the view/form creation process.
- Fixed custom view actions behavior.
- Fixed BigTreeModule::getTagsForItem
- Changed positions to always be position: fixed instead of a mix of fixed and absolute.
- Fixed the variable scope in which _404.php is included on 404 pages.
- Fixed pulling module class' breadcrumb.
- Fixed BigTreeCMS::urlify to properly decode html entities before creating a URL string (prevents this-amp-that type URLs).
- Fixed some z-index issues with dialog windows.
- Fixed Array of Items field type item order to be consistent with List.
- Fixed using view actions (feature, archive, approve, dragging to change position) on items that are not yet published.
- Fixed TinyMCE paste problems.
- Added the ability to specify a required user level for a module action to appear in a module's admin navigation.
- Updated the Home template to default to developer-only and set its position to be second in the list of default templates (so that content is the default for new pages).
- Fixed (Database Populated) List field type not remembering your sort order the first time you create it.
- Fixed Field Types not remembering whether they're allowed for Callouts on initial creation.
- Fixed link to analytics on the dashboard.
- Fixed Feeds not loading properly on the front end.
- Fixed route history not being created when moving pages.
- Fixed CSS border radius in several places in Safari.
- Fixed grouped module breadcrumb going to the wrong place if you clicked the group name.
- Fixed module designer creating the wrong icons and in the wrong order.
- Updated BigTreeAdmin::createModuleAction to allow you to specify a default position.
- Updated view caching to process out {wwwroot}
- Fixed Module View creation to throw proper errors on draggableness (previously checked the wrong properties so false errors were thrown and real ones were missed)
- Added + icons to the edit module screen.
- Silenced some warnings when images had bad EXIF data.
- Made initial content age be the date of installation instead of 1969.
- Fixed install / admin errors when Notices were turned on in PHP.
- Updated the style of the Unused Field adding mechanism to more accurately group the + icon and the field name together.  Thanks philp!
- Fixed the front end editor messing up page titles / nav titles that had & in them.
- Updated sqlfetch() to throw an Exception when you give it a bad sqlquery() result to aid in debugging.
- Added BigTreePaymentGateway -- a way to handle payment gateways without knowing which one the user has.
- Updated the layout of the developer landing to support Payment Gateways.
- Fixed styling of phone / email field types when in callout editor.
- Fixed callout's phone number processing.
- Fixed the initial description of a callout's resources that's written to the callout file.
- Fixed mobile.css and no-zoom/resize being set for mobile browsers (should work now on iPhone/Android, though not optimized for it yet).
- Added placeholder styles for dragging of callouts and image views.
- Fixed image views not using the "prefix" option properly.
- Changed to native event firing on custom Select, Radio, and Checkboxes in the admin (used to be checked:click and select:changed, now you just observe click or changed).
- Fixed Google Analytics and Daily Digest not sending out in the event that your cron isn't running (should have happened on any visit to the admin if cron hasn't run in 24 hours, wasn't)
- Fixed File Browser not working on the front end editor.
- Stopped the home page from being able to be moved.

### 4.0b2
- Removed .htaccess warnings from the installer since it's throwing a lot of warnings when there isn't a problem.
- Fixed page "Revisions" showing the currently published copy as an option for creating a new draft.
- Fixed fatal error that's thrown when an item was locked and someone else tried to access it.
- Updated BigTree::curl to not verify SSL host/peer (caused lots of failed cURLs)
- Fixed a warning thrown when calculating SEO value if some of the field types were arrays in a page template.
- Silenced some warnings in the installer (caused by shared server openbase_dir stuff).
- Fixed styles in the example site.
- Fixed an error that caused issues with grouped views in modules.
- Removed the ability to use Field Wrappers.
- Changed the default sorting for templates.
- Changed the "name" field of resources of callouts to be "title" like everything else (if you've made some callouts, their titles may not be working now, sorry!)
- Fixed some errors in processing photo gallery field types.
- Fixed a bug with file dialogs when hitting Escape to close them.
- Updated the user editor to hide permissions that aren't applicable to Administrators and Developers.
- Fixed text-ellipsis for long URLs in the Properties section of pages.
- Fixing pending changes to pages not decoding properly (caused broken images in HTML areas).
- Added + icons to buttons in forms to bring better attention to them adding things.
- Removed extraneous old code from BigTree 3.3
- Fixed some HTML5 validation errors.
- Fixed a tag closing bug that was causing Internet Explorer to not render the nav properly.
- Fixed a bug with tagging items not sticking.
- Fixed a message when deleting a 404.

### 4.0b1
- Initial public release.
