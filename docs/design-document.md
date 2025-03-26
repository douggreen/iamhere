# I Am Here Communities – Drupal CMS Design Document

Also see [local development](localdev.md) and the [Drupal CMS README.md](drupalcms.md) for more details.

## 1. User Roles and Access Control

The platform will define the following user roles:

- **Anonymous**: Public visitors who can browse basic site content but cannot join, RSVP, or view private profiles/events.
- **Authenticated**: General logged-in users. Limited access until approved.
- **Friend**: A vetted and approved participant in the IAH program. Can RSVP, create events, and leave ratings. This will be implemented as a **role** to simplify permissions.
- **Community Organizer**: Manages a specific Community node, can approve new Friends, manage Organizations and Events within their community.
- **Organizational Ambassador**: Represents a specific Organization. Can edit the Organization node, endorse new Friends, and submit restaurants.
- **Administrator**: Full access to all configuration, content, and user data.

Role permissions will be defined using the Drupal UI and stored in configuration. All configuration (roles, permissions, fields, etc.) will be exported using `drush cex` to the `config/sync` directory and committed to the Git repository.

---

## 2. User Profiles

All user accounts will be extended with additional fields:

- **Profile photo** (required)
- **Primary Organization** (Entity reference to Organization)
- **Additional Organizations** (optional, multiple references)
- **Demographics (optional):**
  - Race / Ethnicity
  - Religion
  - Political Affiliation
  - Age Group
  - Gender Identity
  - Preferred Language
  - Dietary Restrictions
  - Accessibility Needs
  - Zip Code
  - Other (free text)

Each demographic will use **taxonomy reference fields** configured with the **Select (or Other)** pattern.

Users will be able to **edit or remove** any demographic info at any time. Demographics are never shown publicly and are used only for internal matching and diversity analysis.

All profile field definitions and field instances will be configured via the UI, exported using `drush cex`, and tracked in Git.

---

## 3. Node Types

All node types and their fields will be created through the Drupal UI, exported via `drush cex`, and tracked in the Git repository. Any content (e.g., default framework descriptions, guides) will be created via Drupal **recipes** so that local developers can build a complete site without needing access to the production database, avoiding exposure to PII.

### Community
- Title (e.g. "Winchester, VA")
- Address (City and State only, via Address module)
- Community Organizers (user reference, multiple)

Each user can only be a Community Organizer for **one** Community.

### Organization
- Name
- Full Address
- Phone, Email
- Organization Type (Taxonomy: Religious, Civic, Educational, Neighborhood, Social)
- National Affiliation (Taxonomy)
- Ambassador (User reference, 1)

### Participating Restaurant
- Name
- Full Address
- Phone, Email, Contact Name
- Description (freeform)
- Public submission form for restaurants to join

### Event
- Title
- Date/Time
- Host (User reference)
- Number of seats available
- Location Type (Home or Restaurant)
- If Restaurant:
  - Linked Restaurant (entity reference)
- If Home:
  - Meal Type: Hosted / Potluck
  - Bring: Appetizer / Main / Dessert (checkboxes)
  - Dietary Notes (text)
- Discussion Framework (entity reference to Framework)
- Additional Notes
- Status: Draft / Published

### Framework
- Title
- Summary
- Full Description
- External Link (optional)

### Resource
- Title
- Body
- Type (Hosting Guide, Safety, Discussion Tips, Agreement, etc.)
- Attached Media (optional)

---

## 4. Event System

- **Friends** can create Events.
- Events can be marked as hosted in homes or participating restaurants.
- Friends can **request to join** events using the **Flag module**.
- Hosts can **approve/decline** each RSVP.

### Ratings:
- Guests rate the Event (1–5 stars)
- Hosts are rated by guests
- Friends are rated by Hosts and peers
- Uses **Voting API + Fivestar**
- Aggregated ratings displayed on:
  - Event node
  - Host user profile
  - Friend user profile

Configuration of flags, voting fields, and permissions will be managed in the UI and exported via `drush cex`.

---

## 5. Menus and Information Architecture (IA)

Site menus will be configured using the Drupal UI and exported using `drush cex`. Page nodes or blocks referenced in menus will be created as content using recipes where applicable.

### Public Navigation:
- About
- Events Listing
- Join / Sign Up
- Restaurant Signup
- Discussion Frameworks
- Resource Library

### Authenticated Users:
- My Profile
- My Events
- Create Event
- Rate Event
- Messaging (Inbox)

### Community Organizers and Ambassadors:
- Add/Edit Communities / Organizations
- Review RSVPs and Ratings
- Manage Restaurant Submissions
- User Management Tools

---

## 6. Registration Workflow

- Simple sign-up form with:
  - Name
  - Email
  - Profile Photo (required)
  - Primary Organization (autocomplete or add new)

- If "Organization not listed" is selected, a new Organization node is created (unpublished, pending approval).
- After registration, user is redirected to complete optional demographics.
- Users are not full "Friends" until referred and approved.

The registration form will be built using core user fields and custom validation logic. Field definitions will be exported with `drush cex`.

---

## 7. Trust and Safety

- **Referrals**: Friends must be referred by a trusted individual or org rep.
- **Profile Requirements**: Real names and profile photos are mandatory.
- **Friend Agreement**: Must be accepted at sign-up and again before attending meals.
- **Host Training**: Available via video and guides.
- **Meal Safety Rules**:
  - Minimum 8 guests
  - At least 3 women
- **Secure Messaging**: All communication starts on-platform.
- **Ratings**: Every participant rates and is rated after each meal.
- **Reporting**: Hosts can flag problem users for admin review.

User agreement tracking and messaging permissions will be implemented via contrib modules and exported as config.

---

## 8. Discussion Frameworks and Guides

The platform will support **Discussion Frameworks** – structured conversation formats hosts can choose – and provide guides and training materials as static content pages. This ensures hosts have resources to facilitate meaningful dialogue, aligning with the program’s focus on replacing judgment with curiosity during meals.

### Framework Content Type:
- Each Framework node (Jeffersonian Dinner, MADA, etc.) contains a summary and detailed description, plus any relevant external links.
- Hosts can select a framework when creating an event via a dropdown list.
- An info button or icon can show the framework summary in a modal window using Drupal's modal API or a simple tooltip.
- The event display will show the chosen framework's name and a short description.
- Framework nodes will be listed on a public page under "Resources" for general awareness and exploration.

Framework content will be included in site recipes to avoid reliance on production content.

### Guides and Training Resources:
- **Hosting Guide**
- **Safety Guidelines**
- **IAH Friend Agreement**
- **Host Agreement (if distinct)**
- **Discussion Tips**
- **Referral Program Guide**
- **Event Creation Tutorial**
- **FAQ**

Guides will be managed as content (pages or nodes), included in site recipes, and presented through the UI. Training videos will be added as Media items.

---

## 9. Technology Considerations

### Drupal Core Features:
- Entity/Field API, Views, Taxonomy, Media, Content Moderation

### Development Environment:
- DDEV with Drush and Composer
- All configuration exported using `drush cex` to `config/sync` and tracked in Git

### Key Contributed Modules:
- Admin Toolbar
- Pathauto + Token
- Address
- Webform
- Honeypot
- Views Bulk Operations (VBO)
- Redirect
- SMTP / Mail System

### Specialized Contributed Modules:
- Select (or Other)
- Flag
- Voting API + Fivestar
- Private Message (Privatemsg)
- Terms of Use (Agreement)
- Entity Reference Autocomplete

### Missing Modules (to add):
- Select (or Other)
- Flag
- Voting API
- Fivestar
- Private Message
- Terms of Use
- Honeypot
- Pathauto

### Custom Development Areas:
- User Registration Flow
- Event RSVP Interface
- Notifications
- Rating Aggregation
- Access Checks
- Theming
- Scalability

### Security & Privacy:
- HTTPS, secure cookies, role-based access, internal messaging

### Internationalization:
- English-only at launch

### Module References:
- [Webform](https://www.drupal.org/project/webform)
- [Flag](https://www.drupal.org/project/flag)
- [Fivestar](https://www.drupal.org/project/fivestar)
- [Voting API](https://www.drupal.org/project/votingapi)
- [Privatemsg](https://www.drupal.org/project/privatemsg)
- [Terms of Use](https://www.drupal.org/project/terms_of_use)

---

## Summary

This Drupal architecture prioritizes contrib solutions and minimizes custom code, while fulfilling the program’s unique social and trust-building needs. All configuration (roles, permissions, fields, content types, etc.) will be exported with `drush cex` and tracked in Git. Content needed for local development and onboarding will be provided through Drupal recipes, avoiding the need for database sharing and preventing exposure of PII. The system provides flexibility to expand as "I Am Here Communities" grows, supports a diverse set of user roles, and ensures data privacy, community ownership, and mission alignment through structured discussion and relationship-focused tools.
