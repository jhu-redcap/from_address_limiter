# From Address Limiter for REDCap

A REDCap External Module that provides control over the "From Address" field in REDCap alerts, allowing administrators to restrict the use of certain email domains when configuring email alerts in REDCap projects.

## Description

The From Address Limiter module enables administrators to enforce restrictions on the email domains that can be used in the "From Address" field of REDCap alerts. This is particularly useful for ensuring compliance with institutional email policies, preventing unauthorized use of external email addresses, and improving the reliability of email delivery.

The module includes customizable settings for displaying an informative message to users and showing modal alerts to enforce restrictions. It is designed to be easy to configure and directly integrates with the existing REDCap Alerts setup page.

### Features

- **Restrict From Address Domains**: Administrators can define a list of restricted email domains to prevent users from configuring email alerts with restricted domains.
- **Modal Alerts**: A modal alert is displayed if a user attempts to use a restricted email domain. The modal is designed to be truly modal, meaning no other actions can be performed until it is dismissed by the user.

## Usage

Once the module is enabled:

- Navigate to the Alerts setup page in any REDCap project.
- When configuring an email alert, the module will:
    - If a restricted domain is selected in the "From Address" field, a modal alert will be displayed to inform the user that the chosen email domain is not allowed, and the action will be prevented.

**Note**: Currently, this is a system-wide setting that applies to all projects. Future releases may support project-specific settings if needed.

### Input Validation Rules

- **Restricted Domains**: The domains blocked by this module are configurable through system settings, allowing administrators to specify which domains are not allowed. This is particularly useful for blocking public email domains or those that do not align with institutional policies.
- **No Wildcard Support**: The domain list does not support wildcards at this time. Each domain must be explicitly listed, for example: `@example.com, @restricted.org`.

## Configuration

1. Go to **Control Center > External Modules**.
2. Find **From Address Limiter** in the list and click **Configure**.
3. Set the following options:
    - **Forbidden Domains**: A comma-separated list of email domains that are **not allowed** in the "From Address" field. For example: `@gmail.com, @yahoo.com, @publicmail.com`. If no domains are provided, the module will default to allowing all domains.
    - **Alert Message**: The alert message that will be displayed in the modal when a user attempts to use a forbidden domain. The message can include details about why the domain is restricted and must clearly convey the restriction.

## License

This module is licensed under the [MIT License](LICENSE).

Johns Hopkins University 11/2024

