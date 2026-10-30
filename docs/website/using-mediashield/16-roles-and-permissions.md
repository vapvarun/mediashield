# Roles and Permissions

**Included in Free.**

This page says who can manage MediaShield and who can watch.

## Why use it

Staff get the access they need and no more, and members can only see their own progress.

## Managing MediaShield

| Task | Who can do it |
|------|---------------|
| Open the MediaShield screens, change settings, run the wizard | Administrators (users who can manage options) |
| Create and edit videos and playlists | Administrators. The edit screens follow normal WordPress rules for posts. |
| Manage tags, add playlist items, read analytics | Administrators |
| Upload a video file | Anyone holding the `upload_mediashield` capability. Administrators have it by default. |
| End a user's active sessions | Administrators, through the REST request in [Limit Streams and Stop Sharing](09-limit-streams-and-stop-sharing.md) |

## Let another role upload videos

MediaShield has one capability of its own, `upload_mediashield`. To give it to another role, use a role editor plugin, or a few lines in a small plugin:

```php
add_action( 'init', function () {
	$role = get_role( 'editor' );
	if ( $role ) {
		$role->add_cap( 'upload_mediashield' );
	}
} );
```

That lets editors use the upload box on the video edit screen. It does not open the MediaShield admin screens, which stay for administrators.

## Watching

| Viewer | What happens |
|--------|--------------|
| Administrator | Always allowed. Tests as an administrator pass every check. |
| Logged-in user | Allowed unless the video has a role restriction or your domain list blocks the request. |
| Logged-in user without the video's role | Sees Access Denied Text. |
| Guest | Sees the login message while Require Login is on. Can watch when it is off and the video has no role. |

## Members see only their own progress

The My Videos block and shortcode show the signed-in member's own history. Members cannot see other people's data.

## Next steps

- [Restrict Who Can Watch](08-restrict-who-can-watch.md)
- [Access Control](../configuration/04-access-control.md)
