/**
 * Article Statistics Display Plugin - Main JavaScript
 * 
 * Copyright (c) 2026 Kerim Sarıgül
 * Distributed under the GNU GPL v3.
 *
 * Positions stats in three locations:
 * 1. Under title (horizontal)
 * 2. Under abstract (horizontal)
 * 3. Article sidebar (vertical - each stat on its own line)
 * 
 * Also handles summary view: moves stats next to galley buttons
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var settings = window.asdSettings || { position: 'underTitle', style: 'badge' };
        
        // Handle article detail page
        positionStats(settings.position);
        
        // Handle summary/list views - move stats next to galley buttons
        moveSummaryStatsToGalleys();
    });

    /**
     * Move summary stats next to galley buttons in list views
     * Handles different theme structures intelligently
     */
    function moveSummaryStatsToGalleys() {
        var summaryStats = document.querySelectorAll('.asd-stats-summary');
        
        summaryStats.forEach(function(statsEl) {
            // Find the parent article container - expanded selectors for more themes
            var articleContainer = statsEl.closest('.obj_article_summary, .article_summary, .article-summary, article, .media, .card, .article-item');
            if (!articleContainer) {
                articleContainer = statsEl.parentElement;
            }
            
            if (!articleContainer) {
                statsEl.classList.add('asd-moved');
                return;
            }
            
            // Strategy 1: Find galleys_links ul (Default/Manuscript theme)
            var galleysUl = articleContainer.querySelector('ul.galleys_links, .galleys_links');
            if (galleysUl && galleysUl.tagName === 'UL') {
                var li = document.createElement('li');
                li.style.display = 'inline';
                li.appendChild(statsEl);
                galleysUl.appendChild(li);
                statsEl.classList.add('asd-moved');
                return;
            }
            
            // Strategy 2: Find btn-group (Bootstrap theme)
            var btnGroup = articleContainer.querySelector('.btn-group');
            if (btnGroup) {
                // Insert after btn-group, not inside
                if (btnGroup.nextSibling) {
                    btnGroup.parentNode.insertBefore(statsEl, btnGroup.nextSibling);
                } else {
                    btnGroup.parentNode.appendChild(statsEl);
                }
                statsEl.style.marginLeft = '8px';
                statsEl.classList.add('asd-moved');
                return;
            }
            
            // Strategy 3: Find article-summary-galleys (Health Science theme)
            var healthScienceGalleys = articleContainer.querySelector('.article-summary-galleys');
            if (healthScienceGalleys) {
                healthScienceGalleys.appendChild(statsEl);
                statsEl.style.marginLeft = '8px';
                statsEl.style.display = 'inline-block';
                statsEl.classList.add('asd-moved');
                return;
            }
            
            // Strategy 4: Find other galley containers
            var galleyContainers = articleContainer.querySelectorAll('.galleys, .galley-links, .galley_links, .entry_details .galleys, .download-links');
            if (galleyContainers.length > 0) {
                var container = galleyContainers[0];
                container.appendChild(statsEl);
                statsEl.style.marginLeft = '8px';
                statsEl.classList.add('asd-moved');
                return;
            }
            
            // Strategy 5: Find last galley link and insert after
            var galleyLinks = articleContainer.querySelectorAll('.obj_galley_link, a.galley-link, a.galley_link, .btn.galley-link, a[href*="/article/view/"][href*="/galley/"], a[href*="view"][class*="galley"]');
            if (galleyLinks.length > 0) {
                var lastGalley = galleyLinks[galleyLinks.length - 1];
                var parent = lastGalley.parentElement;
                
                // If parent is li, go up one more level
                if (parent.tagName === 'LI') {
                    parent = parent.parentElement;
                }
                
                // Insert after the last galley or its container
                if (lastGalley.nextSibling) {
                    lastGalley.parentNode.insertBefore(statsEl, lastGalley.nextSibling);
                } else {
                    lastGalley.parentNode.appendChild(statsEl);
                }
                statsEl.style.marginLeft = '8px';
                statsEl.classList.add('asd-moved');
                return;
            }
            
            // Fallback: just show in place
            statsEl.classList.add('asd-moved');
        });
    }

    /**
     * Position stats based on selected option (for detail page)
     */
    function positionStats(position) {
        var statsContainer = document.querySelector('.asd-stats-detail');
        if (!statsContainer) return;
        
        var articlePage = document.querySelector('.obj_article_details, .article-details, .article_details');
        if (!articlePage) return;
        
        var targetElement = null;
        
        switch (position) {
            case 'underTitle':
                targetElement = findTitleElement(articlePage);
                if (targetElement) {
                    targetElement.parentNode.insertBefore(statsContainer, targetElement.nextSibling);
                    statsContainer.style.marginTop = '15px';
                    statsContainer.style.marginBottom = '20px';
                    statsContainer.classList.remove('asd-vertical');
                }
                break;
                
            case 'underAbstract':
                targetElement = findAbstractElement(articlePage);
                if (targetElement) {
                    targetElement.parentNode.insertBefore(statsContainer, targetElement.nextSibling);
                    statsContainer.style.marginTop = '20px';
                    statsContainer.classList.remove('asd-vertical');
                }
                break;
                
            case 'sidebar':
                var sidebar = findSidebarElement(articlePage);
                if (sidebar) {
                    // Add vertical class for sidebar display
                    statsContainer.classList.add('asd-vertical');
                    
                    // Create a wrapper for sidebar
                    var wrapper = document.createElement('div');
                    wrapper.className = 'item asd-sidebar-item';
                    wrapper.appendChild(statsContainer);
                    
                    // Try to find galley element to insert after
                    var galleyEl = findGalleyInSidebar(sidebar);
                    if (galleyEl) {
                        // Insert after galley
                        if (galleyEl.nextSibling) {
                            galleyEl.parentNode.insertBefore(wrapper, galleyEl.nextSibling);
                        } else {
                            galleyEl.parentNode.appendChild(wrapper);
                        }
                    } else {
                        // Fallback: insert at beginning
                        var firstItem = sidebar.querySelector('.item, .block');
                        if (firstItem) {
                            sidebar.insertBefore(wrapper, firstItem);
                        } else {
                            sidebar.insertBefore(wrapper, sidebar.firstChild);
                        }
                    }
                }
                break;
        }
    }

    /**
     * Find title element
     */
    function findTitleElement(articlePage) {
        var selectors = [
            '.obj_article_details .page_title',
            '.article-details h1',
            'h1.page_title',
            '.page_title'
        ];
        
        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (el) return el;
        }
        
        return articlePage.querySelector('h1, .title');
    }

    /**
     * Find abstract element
     */
    function findAbstractElement(articlePage) {
        var selectors = [
            '.obj_article_details .abstract',
            '.article-details .abstract',
            '.item.abstract',
            'section.abstract',
            '.abstract'
        ];
        
        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (el) return el;
        }
        
        return null;
    }

    /**
     * Find sidebar element and insert after galleys
     */
    function findSidebarElement(articlePage) {
        var selectors = [
            '.article_sidebar',
            '.article-sidebar',
            '.sidebar',
            '.obj_article_details .entry_details',
            '.entry_details',
            '.article-details aside',
            'aside.sidebar',
            '.col-md-4',  // Bootstrap sidebar column
            '.pkp_structure_sidebar'
        ];
        
        var sidebar = null;
        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (el) {
                sidebar = el;
                break;
            }
        }
        
        if (!sidebar) {
            // Fallback: try to find sidebar by structure
            var mainContent = document.querySelector('.obj_article_details .main_entry, .article-details .main');
            if (mainContent && mainContent.nextElementSibling) {
                sidebar = mainContent.nextElementSibling;
            }
        }
        
        return sidebar;
    }
    
    /**
     * Find galley element in sidebar to insert after
     */
    function findGalleyInSidebar(sidebar) {
        var galleySelectors = [
            '.item.galleys',
            '.galleys',
            '.galley-links',
            '.download'
        ];
        
        for (var i = 0; i < galleySelectors.length; i++) {
            var el = sidebar.querySelector(galleySelectors[i]);
            if (el) return el;
        }
        
        // Try to find by PDF link
        var pdfLink = sidebar.querySelector('a[href*="/galley/"], .obj_galley_link');
        if (pdfLink) {
            return pdfLink.closest('.item') || pdfLink.parentElement;
        }
        
        return null;
    }

})();
