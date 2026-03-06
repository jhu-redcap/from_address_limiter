# From Address Limiter for REDCap

A REDCap External Module that provides control over the "From Address" field in REDCap outgoing emails, allowing administrators to restrict the use to certain email domains when configuring email alerts in REDCap projects.

## Description

The From Address Limiter module enables administrators to enforce restrictions on the email domains that can be used in the "From Address" field of REDCap outgoing emails. This is particularly useful for ensuring compliance with institutional email policies, improving the reliability of email delivery.

The module includes customizable settings for displaying an informative message to users and showing modal alerts to enforce restrictions. It is designed to be easy to configure and directly integrates with the existing REDCap Alerts setup page.

### Features

- **Allowed From Address Domains**: Administrators can define a list of allowed email domains to prevent users from configuring email alerts with domains that may cause other email servers to treat the email as "Spoof".
- **Modal Alerts**: A modal alert is displayed if a user attempts to use a restricted email domain. The modal is designed to be truly modal, meaning no other actions can be performed until it is dismissed by the user.

## Usage

This module checks the selected **From Address** on specific REDCap pages where users can send survey invitations or configure email alerts. If the selected address is not from an allowed domain, the module displays a warning modal and then either blocks the action or allows it to continue, depending on the module setting.

### Pages Affected

The module currently performs validation in the following locations:

#### Alerts & Notifications → **My Alerts**
- Triggered when a user clicks **Add New Alert**
- Triggered when a user edits an existing alert
- The module validates the **From Address** when the alert is saved

#### Data Entry Page → **Survey Options → Compose Survey Invitation**
- Applies only to instruments that are enabled as surveys
- Triggered when the **Send Invitation** button is clicked
- The module validates the selected **From Address** before the invitation is sent

#### Online Designer → **Automated Invitations**
- Triggered when configuring an automated invitation
- The module validates the selected **From Address** when the user clicks:
    - **Save**
    - **Save & Copy to...**

#### Survey Distribution Tools → **Participant List**
- Triggered when sending invitations from the participant list
- The module validates the selected **From Address** when the user clicks **Send Invitations**

---

### Module Behavior

When a user selects a **From Address** whose domain is not included in the module’s allowed domain list, the following behavior occurs depending on the configured action:

- **Prevent**  
  The action is blocked and the user must select a different **From Address**.

- **Notify**  
  A warning modal is displayed, but the user may proceed with the action.

- **Disabled**  
  No validation is performed.

---

### Important Note

This module uses **system-level settings**, meaning the configured domain list and behavior apply to **all projects** on the REDCap instance.

Future versions of the module may support **project-level configuration** if needed.
### Input Validation Rules

- **Allowed Domains**: The domains allowed by this module are configurable through system settings, allowing administrators to specify which domains are allowed. This is particularly useful for blocking public email domains or those that do not align with institutional policies.
- **No Wildcard Support**: The domain list does not support wildcards at this time. Each domain must be explicitly listed, for example: `@jh.edu, @nih.gov, @vumc.org`.

## Configuration

1. Go to **Control Center > External Modules**.
2. Find **From Address Limiter** in the list and click **Configure**.
3. Set the following options:
    - **Allowed Domains**: A comma-separated list of email domains that are **ALLOWED** in the "From Address" field. For example: `@jh.edu, @nih.gov, @vumc.org`. If no domains are provided, the module will default to allowing all domains.
    - **Alert Message**: The alert message that will be displayed in the modal when a user attempts to use a forbidden domain. The message can include details about why the domain is restricted and must clearly convey the restriction.

## License

This module is licensed under the [MIT License](LICENSE).

Johns Hopkins University 03/2026

