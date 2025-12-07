<div class="wrap ppps-admin-wrap">
    <h1><?php _e('PowerPress Podcast Statistics', 'powerpress-podcast-stats'); ?></h1>

    <!-- Add Podcast Feed -->
    <div class="ppps-add-feed-section">
        <h2><?php _e('Add Podcast Feed', 'powerpress-podcast-stats'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="feed-url"><?php _e('Feed URL', 'powerpress-podcast-stats'); ?></label>
                </th>
                <td>
                    <input type="url" id="feed-url" class="regular-text" placeholder="https://yoursite.com/feed/podcast/">
                    <p class="description"><?php _e('Enter the full URL of your podcast feed (must be hosted on this domain)', 'powerpress-podcast-stats'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="feed-name"><?php _e('Podcast Name', 'powerpress-podcast-stats'); ?></label>
                </th>
                <td>
                    <input type="text" id="feed-name" class="regular-text" placeholder="<?php esc_attr_e('My Podcast', 'powerpress-podcast-stats'); ?>">
                    <p class="description"><?php _e('A friendly name to identify this podcast', 'powerpress-podcast-stats'); ?></p>
                </td>
            </tr>
        </table>
        <p>
            <button id="add-feed-btn" class="button button-primary">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Add Feed', 'powerpress-podcast-stats'); ?>
            </button>
        </p>
    </div>

    <!-- Loading -->
    <div id="ppps-loading" class="ppps-loading" style="display: none;">
        <div class="ppps-spinner"></div>
        <p><?php _e('Loading statistics...', 'powerpress-podcast-stats'); ?></p>
    </div>

    <!-- Overview: All Podcasts -->
    <div id="ppps-overview" style="display: none;">
        <h2><?php _e('Your Podcasts', 'powerpress-podcast-stats'); ?></h2>
        <div id="podcasts-grid" class="ppps-podcasts-grid">
            <!-- Filled by JavaScript -->
        </div>
    </div>

    <!-- Detailed View: Single Podcast -->
    <div id="ppps-podcast-detail" style="display: none;">
        <div class="ppps-back-nav">
            <button id="back-to-overview" class="button">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php _e('Back to All Podcasts', 'powerpress-podcast-stats'); ?>
            </button>
        </div>

        <div class="ppps-podcast-header">
            <img id="podcast-thumbnail" src="" alt="" class="ppps-podcast-thumb-large">
            <div class="ppps-podcast-info">
                <h2 id="podcast-title"></h2>
                <p id="podcast-url"></p>
            </div>
        </div>

        <div class="ppps-stat-cards">
            <div class="ppps-stat-card">
                <div class="ppps-stat-icon">
                    <span class="dashicons dashicons-download"></span>
                </div>
                <div class="ppps-stat-content">
                    <h3><?php _e('Total Feed Accesses', 'powerpress-podcast-stats'); ?></h3>
                    <p class="ppps-stat-number" id="podcast-total-accesses">-</p>
                </div>
            </div>
        </div>

        <div class="ppps-charts-row">
            <div class="ppps-chart-container ppps-half">
                <h3><?php _e('Accesses by Platform', 'powerpress-podcast-stats'); ?></h3>
                <div id="chart-by-platform" class="ppps-chart">
                    <p class="ppps-no-data"><?php _e('No data available', 'powerpress-podcast-stats'); ?></p>
                </div>
            </div>

            <div class="ppps-chart-container ppps-half">
                <h3><?php _e('Accesses by Episode', 'powerpress-podcast-stats'); ?></h3>
                <div id="chart-by-episode" class="ppps-chart">
                    <p class="ppps-no-data"><?php _e('No episode data available yet', 'powerpress-podcast-stats'); ?></p>
                </div>
            </div>
        </div>

        <!-- Danger Zone (at the bottom) -->
        <div class="ppps-danger-zone">
            <details>
                <summary><?php _e('Advanced Options', 'powerpress-podcast-stats'); ?></summary>
                <div class="ppps-danger-content">
                    <p class="ppps-danger-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('Warning: This will remove this feed from statistics tracking. The feed itself will continue to work, but it will no longer be monitored.', 'powerpress-podcast-stats'); ?>
                    </p>
                    <button id="remove-feed-from-stats" class="button ppps-danger-button">
                        <?php _e('Remove Feed from Statistics', 'powerpress-podcast-stats'); ?>
                    </button>
                </div>
            </details>
        </div>
    </div>

    <!-- Info Box -->
    <div class="ppps-info-box">
        <h3><?php _e('About These Statistics', 'powerpress-podcast-stats'); ?></h3>
        <ul>
            <li><?php _e('Only feeds you manually add above will be tracked.', 'powerpress-podcast-stats'); ?></li>
            <li><?php _e('Statistics show which podcast platforms and apps access your feeds.', 'powerpress-podcast-stats'); ?></li>
            <li><?php _e('Each unique access is counted once per 30 minutes per platform to avoid duplicates.', 'powerpress-podcast-stats'); ?></li>
            <li><?php _e('Major platforms tracked: Spotify, Apple Podcasts, Pocket Casts, Amazon Music, Podimo, Google Podcasts, iVoox, and Web/Browser.', 'powerpress-podcast-stats'); ?></li>
            <li><?php _e('Check your WordPress debug.log file to see detailed tracking information.', 'powerpress-podcast-stats'); ?></li>
        </ul>
    </div>
</div>
