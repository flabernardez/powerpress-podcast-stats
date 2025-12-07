<div class="wrap ppps-admin-wrap">
    <h1><?php _e('PowerPress Podcast Statistics', 'powerpress-podcast-stats'); ?></h1>
    
    <?php if (empty($feeds)): ?>
    <div class="ppps-no-feeds-notice">
        <div class="notice notice-warning">
            <h2><?php _e('No feeds detected yet', 'powerpress-podcast-stats'); ?></h2>
            <p><?php _e('No podcast feed accesses have been recorded yet. This could be because:', 'powerpress-podcast-stats'); ?></p>
            <ul>
                <li><?php _e('The plugin was just activated and needs time to collect data', 'powerpress-podcast-stats'); ?></li>
                <li><?php _e('Your feeds haven\'t been accessed yet', 'powerpress-podcast-stats'); ?></li>
                <li><?php _e('PowerPress feeds are configured differently than expected', 'powerpress-podcast-stats'); ?></li>
            </ul>
            <p>
                <button id="detect-feeds" class="button button-primary">
                    <span class="dashicons dashicons-search"></span>
                    <?php _e('Detect PowerPress Feeds', 'powerpress-podcast-stats'); ?>
                </button>
                <button id="add-manual-feed" class="button">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add Feed Manually', 'powerpress-podcast-stats'); ?>
                </button>
            </p>
        </div>
        
        <div id="detected-feeds-list" style="display: none;">
            <h3><?php _e('Detected Feeds:', 'powerpress-podcast-stats'); ?></h3>
            <div id="feeds-results"></div>
        </div>
        
        <div id="manual-feed-form" style="display: none;">
            <h3><?php _e('Add Feed Manually', 'powerpress-podcast-stats'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="feed-url"><?php _e('Feed URL', 'powerpress-podcast-stats'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="feed-url" class="regular-text" placeholder="https://yoursite.com/feed/podcast/">
                        <p class="description"><?php _e('Enter the full URL of your podcast feed', 'powerpress-podcast-stats'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="feed-manual-name"><?php _e('Podcast Name', 'powerpress-podcast-stats'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="feed-manual-name" class="regular-text" placeholder="My Podcast">
                        <p class="description"><?php _e('A friendly name to identify this podcast', 'powerpress-podcast-stats'); ?></p>
                    </td>
                </tr>
            </table>
            <p>
                <button id="save-manual-feed" class="button button-primary">
                    <?php _e('Save Feed', 'powerpress-podcast-stats'); ?>
                </button>
                <button id="cancel-manual-feed" class="button">
                    <?php _e('Cancel', 'powerpress-podcast-stats'); ?>
                </button>
            </p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="ppps-filters" <?php echo empty($feeds) ? 'style="display:none;"' : ''; ?>>
        <div class="ppps-filter-group">
            <label for="podcast-select"><?php _e('Podcast:', 'powerpress-podcast-stats'); ?></label>
            <select id="podcast-select" class="ppps-select">
                <option value="0"><?php _e('All Podcasts', 'powerpress-podcast-stats'); ?></option>
                <?php 
                $podcasts_grouped = array();
                foreach ($feeds as $feed) {
                    if (!isset($podcasts_grouped[$feed->podcast_name])) {
                        $podcasts_grouped[$feed->podcast_name] = $feed->id;
                    }
                }
                foreach ($podcasts_grouped as $podcast_name => $podcast_id): 
                ?>
                    <option value="<?php echo esc_attr($podcast_id); ?>">
                        <?php echo esc_html($podcast_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="ppps-filter-group">
            <label for="time-filter"><?php _e('Time Period:', 'powerpress-podcast-stats'); ?></label>
            <select id="time-filter" class="ppps-select">
                <option value="all"><?php _e('All Time', 'powerpress-podcast-stats'); ?></option>
                <option value="week"><?php _e('Last Week', 'powerpress-podcast-stats'); ?></option>
                <option value="month" selected><?php _e('Last Month', 'powerpress-podcast-stats'); ?></option>
                <option value="year"><?php _e('Last Year', 'powerpress-podcast-stats'); ?></option>
                <option value="custom"><?php _e('Custom Range', 'powerpress-podcast-stats'); ?></option>
            </select>
        </div>
        
        <div class="ppps-filter-group ppps-custom-dates" style="display: none;">
            <label for="start-date"><?php _e('From:', 'powerpress-podcast-stats'); ?></label>
            <input type="date" id="start-date" class="ppps-date-input">
            
            <label for="end-date"><?php _e('To:', 'powerpress-podcast-stats'); ?></label>
            <input type="date" id="end-date" class="ppps-date-input">
        </div>
        
        <button id="apply-filters" class="button button-primary">
            <?php _e('Apply Filters', 'powerpress-podcast-stats'); ?>
        </button>
        
        <button id="refresh-stats" class="button">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Refresh', 'powerpress-podcast-stats'); ?>
        </button>
    </div>
    
    <div id="ppps-loading" class="ppps-loading" style="display: none;">
        <div class="ppps-spinner"></div>
        <p><?php _e('Loading statistics...', 'powerpress-podcast-stats'); ?></p>
    </div>
    
    <div id="ppps-stats-container">
        <!-- Stats will be loaded here via AJAX -->
        
        <div class="ppps-stat-cards">
            <div class="ppps-stat-card">
                <div class="ppps-stat-icon">
                    <span class="dashicons dashicons-download"></span>
                </div>
                <div class="ppps-stat-content">
                    <h3><?php _e('Total Feed Accesses', 'powerpress-podcast-stats'); ?></h3>
                    <p class="ppps-stat-number" id="total-accesses">-</p>
                </div>
            </div>
        </div>
        
        <div class="ppps-charts-row">
            <div class="ppps-chart-container ppps-half">
                <h2><?php _e('Accesses by Feed', 'powerpress-podcast-stats'); ?></h2>
                <div id="chart-by-feed" class="ppps-chart">
                    <p class="ppps-no-data"><?php _e('No data available', 'powerpress-podcast-stats'); ?></p>
                </div>
            </div>
            
            <div class="ppps-chart-container ppps-half">
                <h2><?php _e('Accesses by Country', 'powerpress-podcast-stats'); ?></h2>
                <div id="chart-by-country" class="ppps-chart">
                    <p class="ppps-no-data"><?php _e('No data available', 'powerpress-podcast-stats'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="ppps-charts-row">
            <div class="ppps-chart-container ppps-half">
                <h2><?php _e('Accesses by City', 'powerpress-podcast-stats'); ?></h2>
                <div id="chart-by-city" class="ppps-chart">
                    <p class="ppps-no-data"><?php _e('No data available', 'powerpress-podcast-stats'); ?></p>
                </div>
            </div>
            
            <div class="ppps-chart-container ppps-half">
                <h2><?php _e('Top Podcast Apps / Clients', 'powerpress-podcast-stats'); ?></h2>
                <div id="chart-by-agent" class="ppps-chart">
                    <p class="ppps-no-data"><?php _e('No data available', 'powerpress-podcast-stats'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="ppps-chart-container ppps-full">
            <h2><?php _e('Accesses Timeline (Last 30 Days)', 'powerpress-podcast-stats'); ?></h2>
            <div id="chart-timeline" class="ppps-chart ppps-timeline-chart">
                <p class="ppps-no-data"><?php _e('No data available', 'powerpress-podcast-stats'); ?></p>
            </div>
        </div>
    </div>
    
    <div class="ppps-info-box">
        <h3><?php _e('About These Statistics', 'powerpress-podcast-stats'); ?></h3>
        <ul>
            <li><?php _e('Statistics are collected starting from plugin activation - there is no historical data.', 'powerpress-podcast-stats'); ?></li>
            <li><?php _e('Each unique IP is counted once per hour per feed to avoid duplicates.', 'powerpress-podcast-stats'); ?></li>
            <li><?php _e('IPs are hashed for privacy - only location data (country and city) is stored.', 'powerpress-podcast-stats'); ?></li>
            <li><?php _e('Geolocation data is provided by ip-api.com (free service).', 'powerpress-podcast-stats'); ?></li>
            <li><?php _e('Podcast apps cache feeds, so not every play is counted - only feed fetches.', 'powerpress-podcast-stats'); ?></li>
        </ul>
    </div>
    
    <?php if (!empty($feeds)): ?>
    <div class="ppps-managed-feeds">
        <h2><?php _e('Managed Podcast Feeds', 'powerpress-podcast-stats'); ?></h2>
        <p>
            <button id="detect-feeds" class="button">
                <span class="dashicons dashicons-search"></span>
                <?php _e('Detect New Feeds', 'powerpress-podcast-stats'); ?>
            </button>
            <button id="add-manual-feed" class="button">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Add Feed Manually', 'powerpress-podcast-stats'); ?>
            </button>
        </p>
        
        <div id="detected-feeds-list" style="display: none; margin-top: 15px;">
            <h3><?php _e('Detected Feeds:', 'powerpress-podcast-stats'); ?></h3>
            <div id="feeds-results"></div>
        </div>
        
        <div id="manual-feed-form" style="display: none; margin-top: 15px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
            <h3><?php _e('Add Feed Manually', 'powerpress-podcast-stats'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="feed-url"><?php _e('Feed URL', 'powerpress-podcast-stats'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="feed-url" class="regular-text" placeholder="https://yoursite.com/feed/podcast/">
                        <p class="description"><?php _e('Enter the full URL of your podcast feed', 'powerpress-podcast-stats'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="feed-manual-name"><?php _e('Podcast Name', 'powerpress-podcast-stats'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="feed-manual-name" class="regular-text" placeholder="My Podcast">
                        <p class="description"><?php _e('A friendly name to identify this podcast', 'powerpress-podcast-stats'); ?></p>
                    </td>
                </tr>
            </table>
            <p>
                <button id="save-manual-feed" class="button button-primary">
                    <?php _e('Save Feed', 'powerpress-podcast-stats'); ?>
                </button>
                <button id="cancel-manual-feed" class="button">
                    <?php _e('Cancel', 'powerpress-podcast-stats'); ?>
                </button>
            </p>
        </div>
    </div>
    <?php endif; ?>
</div>
