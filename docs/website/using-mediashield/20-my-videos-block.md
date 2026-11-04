# Show Members Their Progress with My Videos

**Included in Free.**

The My Videos block shows a signed-in member the videos they have watched, how far they got and, where your site supplies a watch page, a link to carry on.

## Why use it

Members see their own progress without asking you. It suits course dashboards and member areas.

## Before you start

- Watch history exists only for videos at **Standard** or **Strict** protection. Videos at None or Basic never appear.
- Only published videos are listed.
- A title links somewhere only when an integration supplies the page that shows the video. Learnomy does this for lessons. Without one, titles are plain text. See below.

## Add it to a page

1. Edit the page and click the block inserter.
2. Search for **My Videos** and insert the block. It has no settings. Use the block toolbar for width and the sidebar for spacing.
3. Update the page.

Or use the shortcode anywhere shortcodes work:

```
[mediashield_my_videos]
```

The shortcode prints exactly what the block prints. Before 1.4.1 the shortcode could print nothing on a normal install. Update to fix it.

## What each card shows

- The video's Featured Image, when it has one.
- The title.
- A progress bar and "N% complete".
- **Completed** once the member reaches 100 percent.
- **Resume**, for videos not yet finished, when a watch page is known.

Filter links above the grid switch between All, In Progress and Completed. Newest watched comes first. The list shows 12 videos at a time, with **Previous** and **Next** links below it when there are more. The filter and page are part of the address (for example `?ms_videos=completed&ms_videos_page=2`), so they work without JavaScript and can be bookmarked.

A developer can change how many videos show per page with the `mediashield_my_videos_per_page` filter (up to 100). See [Hooks and Filters](../developer-guide/02-hooks-and-filters.md).

## Where titles and Resume go

Videos have no public page of their own, so MediaShield cannot link to one. In 1.4.1 the block asks for the page through the `mediashield_video_watch_url` filter.

| Your setup | What members get |
|------------|------------------|
| An integration supplies the page, such as the Learnomy lesson that holds the video, or (with Pro) the LearnDash, Tutor LMS or LifterLMS lesson picked in the video's LMS box | The title and Resume link to that page. The player there offers to resume by itself |
| Nothing supplies a page | The title is plain text and there is no Resume link. Progress still shows |

To link to your own pages, a developer adds the filter. See [Hooks and Filters](../developer-guide/02-hooks-and-filters.md).

## What viewers see

A logged-out visitor sees "Please log in to see your video history." A member with no history sees "You have not watched any videos yet." A filter with nothing in it says so, for example "No completed videos yet." A member never sees anyone else's list.

## Check it worked

Watch a Standard video for a minute as a member, then reload the page. The video appears with a percentage.

## Next steps

- [Viewers, Tags and My Videos](14-viewers-tags-and-my-videos.md)
- [Courses with Learnomy](15-courses-with-learnomy.md)
- [Troubleshooting](../troubleshooting/01-common-problems.md)
