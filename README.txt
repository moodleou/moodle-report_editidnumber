Edit ID numbers report
https://moodle.org/plugins/report_editidnumber

This 'report' is actually a tool that lets you edit all the ID numbers for all
the activities and grade items in your course on a single page.

You can install it from the Moodle plugins database using the link above.

Alternatively, you can install it using git. In the top-level folder of your
Moodle install, type the command:
    git clone git://github.com/moodleou/moodle-report_editidnumber.git report/editidnumber
    echo '/report/editidnumber/' >> .git/info/exclude

Then visit the admin screen to allow the install to complete.

Once the plugin is installed, you can access the functionality by going to
Reports -> ID numbers in the Course administration block.
