# Article Statistics Display Plugin for OJS 3.3

Display article view counts and galley download statistics on your Open Journal Systems website.

## Description

This plugin shows article statistics (abstract views and PDF/galley downloads) in a clean, modern design. Statistics can be displayed on article detail pages, issue table of contents, and the homepage.

### Features

- **Multiple Display Locations**: Show statistics on article pages, issue TOC, and homepage
- **Two Visual Styles**: Badge style (colorful cards) or Inline style (clean text)
- **Three Position Options**: Under title, under abstract, or in article sidebar
- **Multi-Theme Support**: Works with Default, Manuscript, Bootstrap3, Health Sciences, and other OJS themes
- **Responsive Design**: Optimized for both desktop and mobile devices
- **Bilingual**: English and Turkish language support

## Requirements

- OJS 3.3.x
- Usage Statistics Plugin must be enabled

## Installation

1. Download the latest release (`articleStatisticsDisplay.tar.gz`)
2. Go to **Settings → Website → Plugins → Upload A New Plugin**
3. Upload the downloaded file
4. Enable the plugin under **Generic Plugins**
5. Click **Settings** to configure display options

## Configuration

### General Settings

- **Display Locations**: Choose where to show statistics
  - Article detail page
  - Issue table of contents
  - Homepage (current issue)

- **Statistics Types**: Select which statistics to display
  - Abstract/article views
  - Galley (PDF, HTML, etc.) downloads

### Article Detail Page Settings

- **Display Style**:
  - Badge Style: Colorful badges with gradient backgrounds
  - Inline Style: Clean text with left border accent

- **Position**:
  - Under article title
  - Under abstract text
  - Article sidebar (vertical layout)

## Screenshots

### Badge Style
```
┌─────────────────────────────────────────────┐
│ [👁 Abstract Views: 338] [⬇ PDF Downloads: 173] │
└─────────────────────────────────────────────┘
```

### Inline Style
```
┃ 👁 Abstract Views: 338 | ⬇ PDF Downloads: 173
```

### List View (Issue TOC / Homepage)
```
[PDF] [👁 366] [⬇ 177]
```

## Compatibility

Tested with the following OJS 3.3 themes:
- Default Theme
- Manuscript Theme
- Bootstrap3 Theme
- Health Sciences Theme

## License

This plugin is licensed under the GNU General Public License v3.0.

## Author

**Kerim Sarıgül**

## Support

For bug reports and feature requests, please use the GitHub Issues page.
