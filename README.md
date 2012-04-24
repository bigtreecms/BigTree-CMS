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
