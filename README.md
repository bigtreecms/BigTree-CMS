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
