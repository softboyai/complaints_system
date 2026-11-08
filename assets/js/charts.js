/**
 * charts.js
 * Initialises Chart.js charts on dashboard pages.
 * Fetches data from api/charts.php.
 * Handles dark mode colour swap.
 */

(function () {
  'use strict';

  // Resolve base URL from a meta tag or window variable set in the page.
  var BASE = window.APP_URL || '';

  var chartGrid = document.querySelector('.chart-grid');
  if (!chartGrid) return;

  var role = chartGrid.dataset.role || 'admin';
  var dept = chartGrid.dataset.dept || '';

  function isDark() { return document.body.classList.contains('dark'); }

  function gridColor()  { return isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)'; }
  function labelColor() { return isDark() ? '#9CA3AF' : '#5B616E'; }

  function buildQuery(type) {
    var q = 'type=' + type;
    if (dept) q += '&dept=' + dept;
    return q;
  }

  // Generic fetch with error handling.
  function fetchChart(type, cb) {
    fetch(BASE + '/api/charts.php?' + buildQuery(type), { credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('Network error');
        return r.json();
      })
      .then(cb)
      .catch(function (err) {
        console.warn('Chart data fetch failed (' + type + '):', err);
      });
  }

  // ---- Status Doughnut ----
  var statusCanvas = document.getElementById('chart-status');
  if (statusCanvas) {
    fetchChart('status', function (data) {
      new Chart(statusCanvas, {
        type: 'doughnut',
        data: {
          labels: data.labels,
          datasets: [{
            data:            data.data,
            backgroundColor: data.colors,
            borderColor:     isDark() ? '#141519' : '#fff',
            borderWidth: 3,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                color:     labelColor(),
                font:      { size: 12, family: 'Inter, system-ui, sans-serif' },
                padding:   12,
                usePointStyle: true,
              }
            },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                  var pct   = total ? Math.round(ctx.raw / total * 100) : 0;
                  return ' ' + ctx.label + ': ' + ctx.raw + ' (' + pct + '%)';
                }
              }
            }
          },
          cutout: '65%',
        }
      });
    });
  }

  // ---- Category Bar ----
  var catCanvas = document.getElementById('chart-category');
  if (catCanvas) {
    fetchChart('category', function (data) {
      new Chart(catCanvas, {
        type: 'bar',
        data: {
          labels: data.labels,
          datasets: [{
            label: 'Complaints',
            data:  data.data,
            backgroundColor: 'rgba(0,82,255,0.7)',
            borderColor:     'rgba(0,82,255,1)',
            borderWidth: 1,
            borderRadius: 4,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false }
          },
          scales: {
            x: {
              ticks: {
                color: labelColor(),
                font:  { size: 11 },
                maxRotation: 30,
              },
              grid: { color: gridColor() }
            },
            y: {
              beginAtZero: true,
              ticks: {
                color: labelColor(),
                stepSize: 1,
                precision: 0,
              },
              grid: { color: gridColor() }
            }
          }
        }
      });
    });
  }

  // ---- Monthly Trend Line ----
  var trendCanvas = document.getElementById('chart-trend');
  if (trendCanvas) {
    fetchChart('trend', function (data) {
      new Chart(trendCanvas, {
        type: 'line',
        data: {
          labels: data.labels,
          datasets: [{
            label: 'Complaints',
            data:  data.data,
            borderColor:     'rgba(0,82,255,1)',
            backgroundColor: 'rgba(0,82,255,0.08)',
            borderWidth: 2,
            pointBackgroundColor: 'rgba(0,82,255,1)',
            pointRadius: 4,
            tension: 0.35,
            fill: true,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false }
          },
          scales: {
            x: {
              ticks: { color: labelColor(), font: { size: 11 } },
              grid:  { color: gridColor() }
            },
            y: {
              beginAtZero: true,
              ticks: { color: labelColor(), stepSize: 1, precision: 0 },
              grid:  { color: gridColor() }
            }
          }
        }
      });
    });
  }

  // Redraw charts when dark mode is toggled.
  var darkToggle = document.getElementById('dark-toggle');
  if (darkToggle) {
    darkToggle.addEventListener('click', function () {
      // Chart.js charts update automatically via re-render triggered by resize.
      // Dispatch a resize event to force redraw with updated colours.
      setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 150);
    });
  }

})();
