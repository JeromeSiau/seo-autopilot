# Team Management Frontend Design

## Overview

Implement frontend team management features: team switcher in sidebar, invite members by email, manage team members, and switch between teams.

## Architecture

### New Frontend Components

```
resources/js/
├── Components/
│   └── TeamSwitcher.tsx          # Dropdown in sidebar
├── Pages/Settings/
│   └── Team.tsx                  # Team management page
```

### New Backend Endpoints

```
POST   /teams/{team}/invitations      # Send invitation
DELETE /teams/{team}/invitations/{id} # Cancel invitation
POST   /teams/{team}/members/{user}   # Change role
DELETE /teams/{team}/members/{user}   # Remove member
PATCH  /teams/{team}                  # Rename team
POST   /teams/switch/{team}           # Switch active team
GET    /invitations/{token}/accept    # Accept invitation (magic link)
```

### New Database Table

```sql
team_invitations
├── id
├── team_id (foreign key)
├── email
├── role (admin/member)
├── token (unique)
├── expires_at
├── created_at
```

## Team Switcher (Sidebar)

**Location:** Top of sidebar, below logo

**Behavior:**
- Shows active team name with role badge (Owner/Admin/Member)
- Dropdown on click with list of user's teams
- Click team → `POST /teams/switch/{team}` → page reload

**Display:**
```
┌─────────────────────────┐
│ 🏢 Acme Corp      Owner ▼│
├─────────────────────────┤
│ ✓ Acme Corp       Owner │
│   Side Project   Member │
│   Client ABC      Admin │
└─────────────────────────┘
```

## Settings > Team Page

### Sections

**1. Team Info (Owner/Admin)**
- Editable team name field
- Save button

**2. Current Members**
- List with avatar, name, email, role badge, join date
- Actions per member:
  - Owner → can change role (dropdown) or remove (except self)
  - Admin → can remove Members only
  - Member → read-only

**3. Pending Invitations (Owner/Admin)**
- List of unaccepted invitations with email, role, sent date
- "Cancel" button for each

**4. Invite Member (Owner/Admin)**
- Form: email + role (Admin/Member)
- "Send invitation" button
- Email sent with link `https://app.../invitations/{token}/accept`

## Invitation Flow

### Sending (Owner/Admin clicks "Invite")

1. `POST /teams/{team}/invitations` with `{email, role}`
2. Create `team_invitations` entry with unique token, expires in 7 days
3. Send email with link `https://app.../invitations/{token}/accept`

### Receiving (recipient clicks link)

```
If logged in with same email:
  → Add to team, redirect dashboard, notification "You joined X"

If logged in with different email:
  → Message "This invitation is for other@email.com"

If not logged in + account exists:
  → Redirect to login, auto-accept after login

If not logged in + no account:
  → Redirect to register pre-filled with email
  → After registration, auto-added to team
```

### Expiration

- Token valid 7 days
- Expired link → "Invitation expired, request a new one" page

## Permissions Matrix

| Feature | Owner | Admin | Member |
|---------|-------|-------|--------|
| View members | ✓ | ✓ | ✓ |
| Invite | ✓ | ✓ | ✗ |
| Change roles | ✓ | ✗ | ✗ |
| Remove member | ✓ | Members only | ✗ |
| Rename team | ✓ | ✓ | ✗ |
| Cancel invitation | ✓ | ✓ | ✗ |

## Files to Create

### Backend
- `database/migrations/xxxx_create_team_invitations_table.php`
- `app/Models/TeamInvitation.php`
- `app/Http/Controllers/TeamController.php`
- `app/Http/Controllers/TeamInvitationController.php`
- `app/Http/Controllers/TeamMemberController.php`
- `app/Mail/TeamInvitationMail.php`
- `resources/views/emails/team-invitation.blade.php`

### Frontend
- `resources/js/Components/TeamSwitcher.tsx`
- `resources/js/Pages/Settings/Team.tsx`

### Routes
- `routes/web.php` - Add team management routes
