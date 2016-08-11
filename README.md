TNS Settings Page
------------------
WordPress plugin to help quickly create custom settings page, grouping options from multiple plugins together.  
Useful for WordPress VIP, where there is no standard plugin infrastructure to create settings pages, or as an alternative 
to hooking standard WordPress options pages.

Use
----
- Extend your class with TNS_Settings_Class
- Implement the set_base_parameters() and register_options_settings() methods to easily register new settings sections and options

v1.0.0
-------
- Basic functionality, auto-creates a single settings page with some sample fields