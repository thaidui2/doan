/**
 * Utility functions for charts in admin dashboard
 */

/**
 * Format currency values for display
 * @param {number} value - Value to format
 * @param {string} locale - Locale to use (default: vi-VN)
 * @param {string} currency - Currency code (default: VND)
 * @returns {string} Formatted currency string
 */
function formatCurrency(value, locale = "vi-VN", currency = "VND") {
  return new Intl.NumberFormat(locale, {
    style: "currency",
    currency: currency,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value);
}

/**
 * Check if Chart.js is loaded and log status
 * @returns {boolean} Whether Chart.js is available
 */
function isChartJsAvailable() {
  const isAvailable = typeof Chart !== "undefined";
  console.log(
    `Chart.js availability: ${
      isAvailable ? "Loaded successfully" : "Not loaded!"
    }`
  );
  return isAvailable;
}

/**
 * Initialize charts with error handling
 * @param {string} chartId - DOM ID of the canvas element
 * @param {Object} config - Chart.js configuration object
 * @returns {Chart|null} Chart instance or null if initialization failed
 */
function initializeChart(chartId, config) {
  try {
    const canvas = document.getElementById(chartId);
    if (!canvas) {
      console.error(`Canvas element with ID ${chartId} not found!`);
      return null;
    }

    if (!isChartJsAvailable()) {
      const errorMsg = "Chart.js library is not loaded!";
      canvas.parentNode.innerHTML = `<div class="alert alert-danger">${errorMsg}</div>`;
      return null;
    }

    return new Chart(canvas, config);
  } catch (error) {
    console.error(`Error initializing chart ${chartId}:`, error);
    const canvas = document.getElementById(chartId);
    if (canvas) {
      canvas.parentNode.innerHTML = `<div class="alert alert-danger">Lỗi khởi tạo biểu đồ: ${error.message}</div>`;
    }
    return null;
  }
}

/**
 * Create a custom error message element
 * @param {string} message - Error message to display
 * @returns {HTMLDivElement} Error message element
 */
function createChartError(message) {
  const errorEl = document.createElement("div");
  errorEl.className = "alert alert-danger";
  errorEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-2"></i> ${message}`;
  return errorEl;
}
