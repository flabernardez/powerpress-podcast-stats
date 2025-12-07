jQuery(document).ready(function($) {
    'use strict';
    
    // Elements
    const $podcastSelect = $('#podcast-select');
    const $timeFilter = $('#time-filter');
    const $customDates = $('.ppps-custom-dates');
    const $startDate = $('#start-date');
    const $endDate = $('#end-date');
    const $applyFilters = $('#apply-filters');
    const $refreshStats = $('#refresh-stats');
    const $loading = $('#ppps-loading');
    const $statsContainer = $('#ppps-stats-container');
    const $detectFeeds = $('#detect-feeds');
    const $addManualFeed = $('#add-manual-feed');
    const $manualFeedForm = $('#manual-feed-form');
    const $saveManualFeed = $('#save-manual-feed');
    const $cancelManualFeed = $('#cancel-manual-feed');
    const $detectedFeedsList = $('#detected-feeds-list');
    
    // Show/hide custom date inputs
    $timeFilter.on('change', function() {
        if ($(this).val() === 'custom') {
            $customDates.slideDown();
        } else {
            $customDates.slideUp();
        }
    });
    
    // Load stats on page load if feeds exist
    if ($podcastSelect.length) {
        loadStats();
    }
    
    // Apply filters button
    $applyFilters.on('click', function() {
        loadStats();
    });
    
    // Refresh button
    $refreshStats.on('click', function() {
        loadStats();
    });
    
    // Enter key in date inputs
    $startDate.add($endDate).on('keypress', function(e) {
        if (e.which === 13) {
            loadStats();
        }
    });
    
    // Detect feeds button
    $detectFeeds.on('click', function() {
        detectPowerPressFeeds();
    });
    
    // Add manual feed button
    $addManualFeed.on('click', function() {
        $detectedFeedsList.hide();
        $manualFeedForm.slideToggle();
    });
    
    // Cancel manual feed
    $cancelManualFeed.on('click', function() {
        $manualFeedForm.slideUp();
        $('#feed-url').val('');
        $('#feed-manual-name').val('');
    });
    
    // Save manual feed
    $saveManualFeed.on('click', function() {
        saveManualFeed();
    });
    
    /**
     * Detect PowerPress feeds
     */
    function detectPowerPressFeeds() {
        $detectedFeedsList.hide();
        $manualFeedForm.hide();
        
        $.ajax({
            url: pppsData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ppps_detect_feeds',
                nonce: pppsData.nonce
            },
            beforeSend: function() {
                $detectFeeds.prop('disabled', true).text('Detecting...');
            },
            success: function(response) {
                if (response.success) {
                    displayDetectedFeeds(response.data);
                } else {
                    alert(response.data || 'Error detecting feeds.');
                }
            },
            error: function() {
                alert('Error detecting feeds. Please try again.');
            },
            complete: function() {
                $detectFeeds.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Detect PowerPress Feeds');
            }
        });
    }
    
    /**
     * Display detected feeds
     */
    function displayDetectedFeeds(feeds) {
        let html = '<table class="widefat"><thead><tr><th>Podcast Name</th><th>Feed URL</th><th>Action</th></tr></thead><tbody>';
        
        feeds.forEach(function(feed) {
            html += '<tr>';
            html += '<td>' + escapeHtml(feed.name) + '</td>';
            html += '<td><code>' + escapeHtml(feed.url) + '</code></td>';
            html += '<td><button class="button button-small register-feed-btn" data-name="' + escapeHtml(feed.name) + '" data-url="' + escapeHtml(feed.url) + '" data-slug="' + escapeHtml(feed.slug) + '">Register</button></td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        
        $('#feeds-results').html(html);
        $detectedFeedsList.slideDown();
        
        // Register button click handler
        $('.register-feed-btn').on('click', function() {
            const $btn = $(this);
            registerDetectedFeed($btn.data('name'), $btn.data('url'), $btn.data('slug'), $btn);
        });
    }
    
    /**
     * Register a detected feed
     */
    function registerDetectedFeed(name, url, slug, $btn) {
        $.ajax({
            url: pppsData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ppps_save_manual_feed',
                nonce: pppsData.nonce,
                podcast_name: name,
                feed_url: url
            },
            beforeSend: function() {
                $btn.prop('disabled', true).text('Registering...');
            },
            success: function(response) {
                if (response.success) {
                    $btn.text('Registered!').removeClass('button-primary').addClass('button-disabled');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    alert(response.data || 'Error registering feed.');
                    $btn.prop('disabled', false).text('Register');
                }
            },
            error: function() {
                alert('Error registering feed. Please try again.');
                $btn.prop('disabled', false).text('Register');
            }
        });
    }
    
    /**
     * Save manual feed
     */
    function saveManualFeed() {
        const feedUrl = $('#feed-url').val().trim();
        const podcastName = $('#feed-manual-name').val().trim();
        
        if (!feedUrl || !podcastName) {
            alert('Please fill in all fields.');
            return;
        }
        
        $.ajax({
            url: pppsData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ppps_save_manual_feed',
                nonce: pppsData.nonce,
                feed_url: feedUrl,
                podcast_name: podcastName
            },
            beforeSend: function() {
                $saveManualFeed.prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    alert('Feed registered successfully!');
                    location.reload();
                } else {
                    alert(response.data || 'Error saving feed.');
                }
            },
            error: function() {
                alert('Error saving feed. Please try again.');
            },
            complete: function() {
                $saveManualFeed.prop('disabled', false).text('Save Feed');
            }
        });
    }
    
    /**
     * Load statistics via AJAX
     */
    function loadStats() {
        const podcastId = $podcastSelect.val();
        const timeFilter = $timeFilter.val();
        const startDate = $startDate.val();
        const endDate = $endDate.val();
        
        // Validate custom dates
        if (timeFilter === 'custom' && (!startDate || !endDate)) {
            alert('Please select both start and end dates.');
            return;
        }
        
        // Show loading
        $loading.show();
        $statsContainer.css('opacity', '0.5');
        
        $.ajax({
            url: pppsData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ppps_get_stats',
                nonce: pppsData.nonce,
                podcast_id: podcastId,
                time_filter: timeFilter,
                start_date: startDate,
                end_date: endDate
            },
            success: function(response) {
                if (response.success) {
                    renderStats(response.data);
                } else {
                    alert('Error loading statistics: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('Error loading statistics. Please try again.');
                console.error('AJAX error:', error);
            },
            complete: function() {
                $loading.hide();
                $statsContainer.css('opacity', '1');
            }
        });
    }
    
    /**
     * Render statistics
     */
    function renderStats(data) {
        // Total accesses
        $('#total-accesses').text(formatNumber(data.total_accesses));
        
        // By feed
        renderBarChart('#chart-by-feed', data.by_feed, 'feed_name');
        
        // By country
        renderBarChart('#chart-by-country', data.by_country, 'country');
        
        // By city
        renderCityChart('#chart-by-city', data.by_city);
        
        // By agent
        renderAgentChart('#chart-by-agent', data.by_agent);
        
        // Timeline
        renderTimeline('#chart-timeline', data.timeline);
    }
    
    /**
     * Render a bar chart
     */
    function renderBarChart(selector, data, labelKey) {
        const $container = $(selector);
        
        if (!data || data.length === 0) {
            $container.html('<p class="ppps-no-data">No data available</p>');
            return;
        }
        
        // Find max value for scaling
        const maxValue = Math.max(...data.map(item => parseInt(item.count)));
        
        // Create chart HTML
        let html = '<div class="ppps-bar-chart">';
        
        data.forEach(item => {
            const label = item[labelKey] || 'Unknown';
            const count = parseInt(item.count);
            const percentage = maxValue > 0 ? (count / maxValue * 100) : 0;
            
            html += `
                <div class="ppps-bar-item">
                    <div class="ppps-bar-label" title="${escapeHtml(label)}">${escapeHtml(label)}</div>
                    <div class="ppps-bar-wrapper">
                        <div class="ppps-bar-fill" style="width: ${percentage}%">
                            <span class="ppps-bar-count">${formatNumber(count)}</span>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        
        $container.html(html);
    }
    
    /**
     * Render city chart (with country)
     */
    function renderCityChart(selector, data) {
        const $container = $(selector);
        
        if (!data || data.length === 0) {
            $container.html('<p class="ppps-no-data">No data available</p>');
            return;
        }
        
        // Find max value for scaling
        const maxValue = Math.max(...data.map(item => parseInt(item.count)));
        
        // Create chart HTML
        let html = '<div class="ppps-bar-chart">';
        
        data.forEach(item => {
            const city = item.city || 'Unknown';
            const country = item.country || '';
            const label = country ? `${city}, ${country}` : city;
            const count = parseInt(item.count);
            const percentage = maxValue > 0 ? (count / maxValue * 100) : 0;
            
            html += `
                <div class="ppps-bar-item">
                    <div class="ppps-bar-label" title="${escapeHtml(label)}">${escapeHtml(label)}</div>
                    <div class="ppps-bar-wrapper">
                        <div class="ppps-bar-fill" style="width: ${percentage}%">
                            <span class="ppps-bar-count">${formatNumber(count)}</span>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        
        $container.html(html);
    }
    
    /**
     * Render agent chart (simplified user agents)
     */
    function renderAgentChart(selector, data) {
        const $container = $(selector);
        
        if (!data || data.length === 0) {
            $container.html('<p class="ppps-no-data">No data available</p>');
            return;
        }
        
        // Find max value for scaling
        const maxValue = Math.max(...data.map(item => parseInt(item.count)));
        
        // Create chart HTML
        let html = '<div class="ppps-bar-chart">';
        
        data.forEach(item => {
            const agent = simplifyUserAgent(item.user_agent);
            const count = parseInt(item.count);
            const percentage = maxValue > 0 ? (count / maxValue * 100) : 0;
            
            html += `
                <div class="ppps-bar-item">
                    <div class="ppps-bar-label" title="${escapeHtml(item.user_agent)}">${escapeHtml(agent)}</div>
                    <div class="ppps-bar-wrapper">
                        <div class="ppps-bar-fill" style="width: ${percentage}%">
                            <span class="ppps-bar-count">${formatNumber(count)}</span>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        
        $container.html(html);
    }
    
    /**
     * Render timeline chart
     */
    function renderTimeline(selector, data) {
        const $container = $(selector);
        
        if (!data || data.length === 0) {
            $container.html('<p class="ppps-no-data">No data available</p>');
            return;
        }
        
        // Find max value for scaling
        const maxValue = Math.max(...data.map(item => parseInt(item.count)));
        
        // Create timeline HTML
        let html = '<div class="ppps-timeline">';
        
        data.forEach(item => {
            const date = item.date;
            const count = parseInt(item.count);
            const height = maxValue > 0 ? (count / maxValue * 100) : 0;
            const displayDate = formatDate(date);
            
            html += `
                <div class="ppps-timeline-bar" style="height: ${height}%">
                    <div class="ppps-tooltip">
                        ${displayDate}<br>
                        <strong>${formatNumber(count)} accesses</strong>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        
        $container.html(html);
    }
    
    /**
     * Simplify user agent string
     */
    function simplifyUserAgent(agent) {
        if (!agent) return 'Unknown';
        
        // Common podcast apps
        const patterns = {
            'Apple Podcasts': /Apple.*Podcasts|iTunes/i,
            'Spotify': /Spotify/i,
            'Google Podcasts': /Google.*Podcasts/i,
            'Overcast': /Overcast/i,
            'Pocket Casts': /Pocket.*Casts/i,
            'Castro': /Castro/i,
            'Podcast Addict': /Podcast.*Addict/i,
            'Player FM': /Player.*FM/i,
            'Castbox': /Castbox/i,
            'Stitcher': /Stitcher/i,
            'TuneIn': /TuneIn/i,
            'iCatcher': /iCatcher/i,
            'Downcast': /Downcast/i,
            'AntennaPod': /AntennaPod/i,
            'Podcast Republic': /Podcast.*Republic/i,
            'Chrome': /Chrome/i,
            'Firefox': /Firefox/i,
            'Safari': /Safari/i,
            'Edge': /Edge/i
        };
        
        for (const [name, pattern] of Object.entries(patterns)) {
            if (pattern.test(agent)) {
                return name;
            }
        }
        
        // Truncate if too long
        if (agent.length > 40) {
            return agent.substring(0, 37) + '...';
        }
        
        return agent;
    }
    
    /**
     * Format number with thousands separator
     */
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    /**
     * Format date
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        const options = { month: 'short', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
});
