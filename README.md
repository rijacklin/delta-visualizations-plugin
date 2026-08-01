Delta Moodle Plugin
=======================
* Maintained by: Richard Jacklin <rijacklin1@gmail.com>
* Copyright: 2026

Description
===========

The DELTA Visualizations Moodle plugin provides a dashboard block for teachers that allows them to visualize the learning behaviours being exhibited in their courses from real-time Moodle data. Teachers can select one or more of their courses, compare teacher and student behaviours, and filter chart data by reporting period for time-based learning behaviours.

Installation
============

## Installation

* Download `delta_visualizations.zip`

### Automatic Installation

* Click "Site administration" at the top of your Moodle site.
* Click "Plugins" from "Site administration" page sub menu, then click "Install plugins".
* Under "Install plugin from ZIP file", click "Choose a file..." to select the downloaded `delta_visualizations.zip` file from your file system.

### Manual Installation

* Extract the "delta_visualizations" folder from `delta_visualizations.zip`
* Navigate to the `/public` subdirectory of the moodle directory on your file system.
* Place the "delta_visualizations" folder within the `/public/blocks` subdirectory.
* Click "Site administration" at the top of your Moodle site and follow the installation process.

## Usage

1. Enable **Edit mode** on the Moodle Dashboard.
2. Add the **DELTA Visualizations** block.
3. Select one or more courses from the course selector and click **Apply**.
4. Switch between the teacher and student tabs, then expand an indicator to see its chart.
5. For a chart with a **Reporting period** control, select a period and click **Apply** to refresh that chart without reloading the page.

## Configuration

The DELTA visualizations plugin provides five site-wide settings. These settings reflect institution-wide preferences.

| Setting | Default | Accepted values | Purpose |
| --- | ---: | --- | --- |
| Struggling-student grade cutoff | 70 | 0–100 | Grades below this percentage identify students who may require targeted feedback. |
| Personalized-feedback uniqueness goal | 60 | 0–100 | Minimum acceptable uniquess percentage between feedback messages. |
| Weekly interaction threshold | 5 | 0 or greater | Minimum average number of teacher interactions per week used by the Consistent Use of LMS indicator. |
| Timely feedback response window | 8 days | 0–31 days | Maximum time between a student submission and teacher feedback. |
| Estimated session cap | 30 minutes | 1 minute–1 day | Maximum duration attributed to an activity when there is no explicit end or logout event. |
