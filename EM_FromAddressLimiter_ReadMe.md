From Address Limiter Module

# Purpose

When an email is sent, it includes an unseen header that provides important information to the receiving email server. Included in this information is the domain of the sending server. Most receiving email servers compare this domain with the actual "From" address looking for a mismatch. When an email is received with a "From" address that does not match the sending server's domain, it is referred to as "Spoofing". In most cases, the receiving email server will reject these messages, and they will never reach the intended recipient. This has important implications:

- When an automated survey invitation (ASI) is triggered, REDCap simply hands it off to the local email server for distribution.
- REDCap has no idea if it was successfully delivered or rejected
- Further, ASI's and Alerts/Notifications are tagged by REDCap as "sent", meaning they cannot be easily "re-sent" once a "From" address issue is resolved.

What drives these situations? There are several scenarios where this might occur:

- Consider a multi-site project where DAG's are being used, and individuals from other institutions have access. If survey invitations or alerts are configured with the "From" address of someone from another institution, there is a very good chance that any survey invitations and/or alerts will never be received and REDCap will have knowledge of the rejected email.
- A user may add a secondary or tertiary email address to their REDCap user account that is not associated with the institutional email domain. If that email is used as the "From" address, survey invitations and/or alerts will likely never be received.
- Some studies provide REDCap access to study sponsors. If their email address is used for ASI's or Alerts/Notifications, those messages will likely never be received by study participants.

The **From Address Limiter** module provides the ability to restrict "From" addresses based on the institution's email domain(s). For example, "University Health System" can limit "From" addresses to those having the **@university.edu** and/or **@university.mail.edu** domain(s).