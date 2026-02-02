/**
 * Nepali Date Picker - Calendar Style
 * Supports both BS (Bikram Sambat) and AD (Gregorian) calendars
 * Trishakti Apparel Verification Portal
 *
 * Can load data from XML file via API or use built-in data
 */

// BS calendar data - days in each month for years 2060-2100
// This is the default/fallback data. Can be overridden by XML data.
let bsMonthDays = {
    2060: [30,32,31,32,31,30,30,30,29,30,29,31],
    2061: [31,31,32,31,31,31,30,29,30,29,30,30],
    2062: [31,31,32,32,31,30,30,29,30,29,30,30],
    2063: [31,32,31,32,31,30,30,30,29,29,30,31],
    2064: [30,32,31,32,31,30,30,30,29,30,29,31],
    2065: [31,31,32,31,31,31,30,29,30,29,30,30],
    2066: [31,31,32,32,31,30,30,29,30,29,30,30],
    2067: [31,32,31,32,31,30,30,30,29,29,30,31],
    2068: [31,31,31,32,31,31,29,30,30,29,29,31],
    2069: [31,31,32,31,31,31,30,29,30,29,30,30],
    2070: [31,31,32,32,31,30,30,29,30,29,30,30],
    2071: [31,32,31,32,31,30,30,30,29,29,30,31],
    2072: [30,32,31,32,31,30,30,30,29,30,29,31],
    2073: [31,31,32,31,31,31,30,29,30,29,30,30],
    2074: [31,31,32,32,31,30,30,29,30,29,30,30],
    2075: [31,32,31,32,31,30,30,30,29,29,30,31],
    2076: [30,32,31,32,31,31,29,30,30,29,29,31],
    2077: [31,31,32,31,31,31,30,29,30,29,30,30],
    2078: [31,31,32,32,31,30,30,29,30,29,30,30],
    2079: [31,32,31,32,31,30,30,30,29,29,30,31],
    2080: [31,31,31,32,31,31,29,30,30,29,30,30],
    2081: [31,31,32,31,31,31,30,29,30,29,30,30],
    2082: [31,31,32,32,31,30,30,29,30,29,30,30],
    2083: [31,32,31,32,31,30,30,30,29,29,30,31],
    2084: [31,31,31,32,31,31,29,30,30,29,30,30],
    2085: [31,31,32,31,31,31,30,29,30,29,30,30],
    2086: [31,32,31,32,31,30,30,29,30,29,30,30],
    2087: [31,32,31,32,31,30,30,30,29,29,30,31],
    2088: [30,32,31,32,31,30,30,30,29,30,29,31],
    2089: [31,31,32,31,31,31,30,29,30,29,30,30],
    2090: [31,31,32,32,31,30,30,29,30,29,30,30],
    2091: [31,32,31,32,31,30,30,30,29,29,30,31],
    2092: [30,32,31,32,31,30,30,30,29,30,29,31],
    2093: [31,31,32,31,31,31,30,29,30,29,30,30],
    2094: [31,31,32,32,31,30,30,29,30,29,30,30],
    2095: [31,32,31,32,31,30,30,30,29,29,30,31],
    2096: [30,32,31,32,31,31,29,30,30,29,29,31],
    2097: [31,31,32,31,31,31,30,29,30,29,30,30],
    2098: [31,31,32,32,31,30,30,29,30,29,30,30],
    2099: [31,32,31,32,31,30,30,30,29,29,30,31],
    2100: [31,31,31,32,31,31,29,30,30,29,30,30]
};

// BS to AD direct mappings from XML (if loaded)
let bsToAdMappings = {};
let adToBsMappings = {}; // Reverse mapping for faster lookup
let xmlDataLoaded = false;
let xmlYearRange = { min: 2060, max: 2100 };

/**
 * Load date data from XML API
 * @param {string} apiUrl - URL to the API endpoint
 * @param {function} callback - Callback function when data is loaded
 */
function loadDateDataFromXml(apiUrl, callback) {
    fetch(apiUrl + '?action=calendar')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.daysPerMonth) {
                // Update bsMonthDays with data from XML
                for (const year in data.daysPerMonth) {
                    const yearInt = parseInt(year);
                    const months = data.daysPerMonth[year];
                    bsMonthDays[yearInt] = [];
                    for (let m = 1; m <= 12; m++) {
                        bsMonthDays[yearInt].push(months[m] || 30);
                    }
                }

                // Update year range
                if (data.range) {
                    if (data.range.min_bs) {
                        xmlYearRange.min = parseInt(data.range.min_bs.split('-')[0]);
                    }
                    if (data.range.max_bs) {
                        xmlYearRange.max = parseInt(data.range.max_bs.split('-')[0]);
                    }
                }

                xmlDataLoaded = true;
                console.log('Nepali date data loaded from XML:', Object.keys(data.daysPerMonth).length, 'years');
            }
            if (callback) callback(data);
        })
        .catch(err => {
            console.warn('Could not load date data from XML, using built-in data:', err);
            if (callback) callback(null);
        });
}

/**
 * Load full mappings from XML API (for direct date lookup)
 * @param {string} apiUrl - URL to the API endpoint
 * @param {function} callback - Callback function when data is loaded
 */
function loadDateMappingsFromXml(apiUrl, callback) {
    fetch(apiUrl + '?action=mappings')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                bsToAdMappings = data.data;
                console.log('Nepali date mappings loaded:', data.count, 'entries');
            }
            if (callback) callback(data);
        })
        .catch(err => {
            console.warn('Could not load date mappings from XML:', err);
            if (callback) callback(null);
        });
}

// Month names
const bsMonthNames = ['बैशाख', 'जेष्ठ', 'आषाढ', 'श्रावण', 'भाद्र', 'आश्विन', 'कार्तिक', 'मंसिर', 'पौष', 'माघ', 'फाल्गुन', 'चैत्र'];
const bsMonthNamesEn = ['Baisakh', 'Jestha', 'Ashadh', 'Shrawan', 'Bhadra', 'Ashwin', 'Kartik', 'Mangsir', 'Poush', 'Magh', 'Falgun', 'Chaitra'];
const adMonthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

// Day names
const dayNamesShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const dayNamesNp = ['आइत', 'सोम', 'मंगल', 'बुध', 'बिहि', 'शुक्र', 'शनि'];

// Reference date: 2081-01-01 BS = 2024-04-13 AD (Saturday)
const refBsYear = 2081, refBsMonth = 1, refBsDay = 1;
const refAdDate = new Date(2024, 3, 13);
const refDayOfWeek = 6; // Saturday

// Auto-load XML data on script load
let xmlDataLoadPromise = null;

/**
 * Auto-load XML data from the API
 * Call this function to preload date mappings
 */
function autoLoadNepaliDateData(apiUrl) {
    if (xmlDataLoadPromise) return xmlDataLoadPromise;

    xmlDataLoadPromise = new Promise((resolve) => {
        fetch(apiUrl + '?action=mappings')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    bsToAdMappings = data.data;
                    // Create reverse mapping for faster AD to BS lookup
                    adToBsMappings = {};
                    for (const bsKey in bsToAdMappings) {
                        adToBsMappings[bsToAdMappings[bsKey]] = bsKey;
                    }
                    xmlDataLoaded = true;
                    console.log('Nepali date mappings loaded:', Object.keys(bsToAdMappings).length, 'entries');
                }
                resolve(true);
            })
            .catch(err => {
                console.warn('Could not load date mappings from XML, using built-in data:', err);
                resolve(false);
            });
    });

    return xmlDataLoadPromise;
}

/**
 * Initialize a Nepali Date Picker with Calendar
 * @param {string} containerId - The container element ID
 * @param {string} bsFieldName - Name for the BS date hidden input
 * @param {string} adFieldName - Name for the AD date hidden input
 * @param {string} initialBsDate - Initial BS date (optional, defaults to today)
 * @param {string} apiUrl - URL to the date API (optional, for loading XML data)
 */
function initNepaliDatePicker(containerId, bsFieldName, adFieldName, initialBsDate = null, apiUrl = null) {
    // If API URL provided, load data first then initialize
    if (apiUrl && !xmlDataLoaded) {
        autoLoadNepaliDateData(apiUrl).then(() => {
            _initDatePickerInternal(containerId, bsFieldName, adFieldName, initialBsDate);
        });
        return;
    }

    _initDatePickerInternal(containerId, bsFieldName, adFieldName, initialBsDate);
}

/**
 * Internal date picker initialization
 */
function _initDatePickerInternal(containerId, bsFieldName, adFieldName, initialBsDate) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const pickerId = containerId + '_picker';

    // Get today's date as default
    let selectedAdDate = new Date();
    let todayBs = adToBs(selectedAdDate);

    let selectedBsYear = todayBs.year;
    let selectedBsMonth = todayBs.month;
    let selectedBsDay = todayBs.day;

    // If initial date provided, use it instead
    if (initialBsDate && initialBsDate !== '2081-01-01') {
        let initParts = initialBsDate.split('-');
        if (initParts.length === 3) {
            selectedBsYear = parseInt(initParts[0]) || todayBs.year;
            selectedBsMonth = parseInt(initParts[1]) || todayBs.month;
            selectedBsDay = parseInt(initParts[2]) || todayBs.day;
            selectedAdDate = bsToAd(selectedBsYear, selectedBsMonth, selectedBsDay);
        }
    }

    // State
    let isOpen = false;
    let dateMode = 'bs';
    let viewBsYear = selectedBsYear;
    let viewBsMonth = selectedBsMonth;
    let viewAdYear = selectedAdDate.getFullYear();
    let viewAdMonth = selectedAdDate.getMonth() + 1;

    // Build HTML
    container.innerHTML = `
        <div class="nepali-dp-display" id="${pickerId}_display">
            <div class="nepali-dp-dates">
                <div class="nepali-dp-bs">
                    <span class="nepali-dp-label">BS:</span>
                    <span class="nepali-dp-value" id="${pickerId}_bs_show">${formatBsDate(selectedBsYear, selectedBsMonth, selectedBsDay)}</span>
                </div>
                <span class="nepali-dp-sep">|</span>
                <div class="nepali-dp-ad">
                    <span class="nepali-dp-label">AD:</span>
                    <span class="nepali-dp-value" id="${pickerId}_ad_show">${formatAdDate(selectedAdDate)}</span>
                </div>
            </div>
            <i class="fas fa-calendar-alt nepali-dp-arrow" id="${pickerId}_arrow"></i>
        </div>

        <div class="nepali-dp-panel hidden" id="${pickerId}_panel">
            <div class="nepali-dp-toggle">
                <button type="button" class="nepali-dp-btn-bs active" id="${pickerId}_btn_bs">
                    <i class="fas fa-calendar-alt"></i> BS Calendar
                </button>
                <button type="button" class="nepali-dp-btn-ad" id="${pickerId}_btn_ad">
                    <i class="fas fa-calendar"></i> AD Calendar
                </button>
            </div>
            <div class="nepali-dp-calendar" id="${pickerId}_calendar"></div>
            <div class="nepali-dp-footer">
                <button type="button" class="nepali-dp-today" id="${pickerId}_today">
                    <i class="fas fa-crosshairs"></i> Today
                </button>
                <button type="button" class="nepali-dp-apply" id="${pickerId}_apply">
                    <i class="fas fa-check"></i> Apply
                </button>
            </div>
        </div>

        <input type="hidden" name="${bsFieldName}" id="${pickerId}_bs_val" value="${formatBsDate(selectedBsYear, selectedBsMonth, selectedBsDay)}">
        <input type="hidden" name="${adFieldName}" id="${pickerId}_ad_val" value="${formatAdDate(selectedAdDate)}">
    `;

    // Get elements
    const displayEl = document.getElementById(`${pickerId}_display`);
    const panelEl = document.getElementById(`${pickerId}_panel`);
    const arrowEl = document.getElementById(`${pickerId}_arrow`);
    const btnBsEl = document.getElementById(`${pickerId}_btn_bs`);
    const btnAdEl = document.getElementById(`${pickerId}_btn_ad`);
    const calendarEl = document.getElementById(`${pickerId}_calendar`);
    const todayBtnEl = document.getElementById(`${pickerId}_today`);
    const applyBtnEl = document.getElementById(`${pickerId}_apply`);

    // Toggle panel
    displayEl.addEventListener('click', function(e) {
        e.stopPropagation();
        isOpen = !isOpen;
        panelEl.classList.toggle('hidden', !isOpen);
        arrowEl.classList.toggle('rotate-180', isOpen);
        if (isOpen) renderCalendar();
    });

    // Close on outside click
    document.addEventListener('click', function(e) {
        if (isOpen && !panelEl.contains(e.target) && !displayEl.contains(e.target)) {
            isOpen = false;
            panelEl.classList.add('hidden');
            arrowEl.classList.remove('rotate-180');
        }
    });

    // BS button
    btnBsEl.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dateMode = 'bs';
        btnBsEl.classList.add('active');
        btnAdEl.classList.remove('active');
        viewBsYear = selectedBsYear;
        viewBsMonth = selectedBsMonth;
        renderCalendar();
    });

    // AD button
    btnAdEl.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dateMode = 'ad';
        btnAdEl.classList.add('active');
        btnBsEl.classList.remove('active');
        viewAdYear = selectedAdDate.getFullYear();
        viewAdMonth = selectedAdDate.getMonth() + 1;
        renderCalendar();
    });

    // Today button
    todayBtnEl.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const today = new Date();
        const todayBs = adToBs(today);

        selectedAdDate = today;
        selectedBsYear = todayBs.year;
        selectedBsMonth = todayBs.month;
        selectedBsDay = todayBs.day;

        viewAdYear = today.getFullYear();
        viewAdMonth = today.getMonth() + 1;
        viewBsYear = todayBs.year;
        viewBsMonth = todayBs.month;

        renderCalendar();
        updateDisplays();
    });

    // Apply button
    applyBtnEl.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        updateDisplays();
        isOpen = false;
        panelEl.classList.add('hidden');
        arrowEl.classList.remove('rotate-180');
    });

    // Render calendar
    function renderCalendar() {
        if (dateMode === 'bs') {
            renderBsCalendar();
        } else {
            renderAdCalendar();
        }
    }

    // Render BS Calendar
    function renderBsCalendar() {
        const daysInMonth = bsMonthDays[viewBsYear] ? bsMonthDays[viewBsYear][viewBsMonth - 1] : 30;
        const firstDayOfWeek = getBsFirstDayOfWeek(viewBsYear, viewBsMonth);

        let html = `
            <div class="ndp-cal-header">
                <button type="button" class="ndp-nav-btn" id="${pickerId}_prev"><i class="fas fa-chevron-left"></i></button>
                <div class="ndp-month-year">
                    <select class="ndp-month-sel" id="${pickerId}_month_sel">
                        ${bsMonthNames.map((name, i) =>
                            `<option value="${i + 1}" ${i + 1 === viewBsMonth ? 'selected' : ''}>${name}</option>`
                        ).join('')}
                    </select>
                    <select class="ndp-year-sel" id="${pickerId}_year_sel">
                        ${generateYearOptions(2060, 2100, viewBsYear, true)}
                    </select>
                </div>
                <button type="button" class="ndp-nav-btn" id="${pickerId}_next"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="ndp-weekdays">
                ${dayNamesNp.map((day, i) => `<div class="ndp-weekday${i === 6 ? ' sat' : ''}">${day}</div>`).join('')}
            </div>
            <div class="ndp-days">
        `;

        // Empty cells before first day
        for (let i = 0; i < firstDayOfWeek; i++) {
            html += `<div class="ndp-day empty"></div>`;
        }

        // Days of month
        for (let day = 1; day <= daysInMonth; day++) {
            const isSelected = viewBsYear === selectedBsYear && viewBsMonth === selectedBsMonth && day === selectedBsDay;
            const dayOfWeek = (firstDayOfWeek + day - 1) % 7;
            const isSat = dayOfWeek === 6;
            html += `<div class="ndp-day${isSelected ? ' selected' : ''}${isSat ? ' sat' : ''}" data-day="${day}">${day}</div>`;
        }

        html += `</div>`;
        calendarEl.innerHTML = html;
        bindCalendarEvents('bs');
    }

    // Render AD Calendar
    function renderAdCalendar() {
        const daysInMonth = new Date(viewAdYear, viewAdMonth, 0).getDate();
        const firstDay = new Date(viewAdYear, viewAdMonth - 1, 1).getDay();

        let html = `
            <div class="ndp-cal-header">
                <button type="button" class="ndp-nav-btn" id="${pickerId}_prev"><i class="fas fa-chevron-left"></i></button>
                <div class="ndp-month-year">
                    <select class="ndp-month-sel" id="${pickerId}_month_sel">
                        ${adMonthNames.map((name, i) =>
                            `<option value="${i + 1}" ${i + 1 === viewAdMonth ? 'selected' : ''}>${name}</option>`
                        ).join('')}
                    </select>
                    <select class="ndp-year-sel" id="${pickerId}_year_sel">
                        ${generateYearOptions(2000, 2050, viewAdYear)}
                    </select>
                </div>
                <button type="button" class="ndp-nav-btn" id="${pickerId}_next"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="ndp-weekdays">
                ${dayNamesShort.map((day, i) => `<div class="ndp-weekday${i === 0 ? ' sun' : ''}${i === 6 ? ' sat' : ''}">${day}</div>`).join('')}
            </div>
            <div class="ndp-days">
        `;

        // Empty cells
        for (let i = 0; i < firstDay; i++) {
            html += `<div class="ndp-day empty"></div>`;
        }

        // Days
        const selDay = selectedAdDate.getDate();
        const selMonth = selectedAdDate.getMonth() + 1;
        const selYear = selectedAdDate.getFullYear();

        for (let day = 1; day <= daysInMonth; day++) {
            const isSelected = viewAdYear === selYear && viewAdMonth === selMonth && day === selDay;
            const dow = new Date(viewAdYear, viewAdMonth - 1, day).getDay();
            const isSun = dow === 0;
            const isSat = dow === 6;
            html += `<div class="ndp-day${isSelected ? ' selected' : ''}${isSun ? ' sun' : ''}${isSat ? ' sat' : ''}" data-day="${day}">${day}</div>`;
        }

        html += `</div>`;
        calendarEl.innerHTML = html;
        bindCalendarEvents('ad');
    }

    // Bind calendar events
    function bindCalendarEvents(mode) {
        // Previous
        document.getElementById(`${pickerId}_prev`).addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (mode === 'bs') {
                viewBsMonth--;
                if (viewBsMonth < 1) { viewBsMonth = 12; viewBsYear--; }
            } else {
                viewAdMonth--;
                if (viewAdMonth < 1) { viewAdMonth = 12; viewAdYear--; }
            }
            renderCalendar();
        });

        // Next
        document.getElementById(`${pickerId}_next`).addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (mode === 'bs') {
                viewBsMonth++;
                if (viewBsMonth > 12) { viewBsMonth = 1; viewBsYear++; }
            } else {
                viewAdMonth++;
                if (viewAdMonth > 12) { viewAdMonth = 1; viewAdYear++; }
            }
            renderCalendar();
        });

        // Month select
        document.getElementById(`${pickerId}_month_sel`).addEventListener('change', function(e) {
            e.stopPropagation();
            if (mode === 'bs') viewBsMonth = parseInt(this.value);
            else viewAdMonth = parseInt(this.value);
            renderCalendar();
        });

        // Year select
        document.getElementById(`${pickerId}_year_sel`).addEventListener('change', function(e) {
            e.stopPropagation();
            if (mode === 'bs') viewBsYear = parseInt(this.value);
            else viewAdYear = parseInt(this.value);
            renderCalendar();
        });

        // Day click
        calendarEl.querySelectorAll('.ndp-day:not(.empty)').forEach(dayEl => {
            dayEl.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const day = parseInt(this.dataset.day);

                if (mode === 'bs') {
                    selectedBsYear = viewBsYear;
                    selectedBsMonth = viewBsMonth;
                    selectedBsDay = day;
                    selectedAdDate = bsToAd(selectedBsYear, selectedBsMonth, selectedBsDay);
                } else {
                    selectedAdDate = new Date(viewAdYear, viewAdMonth - 1, day);
                    const bs = adToBs(selectedAdDate);
                    selectedBsYear = bs.year;
                    selectedBsMonth = bs.month;
                    selectedBsDay = bs.day;
                }

                renderCalendar();
                updateDisplays();
            });
        });
    }

    // Update displays
    function updateDisplays() {
        const bsStr = formatBsDate(selectedBsYear, selectedBsMonth, selectedBsDay);
        const adStr = formatAdDate(selectedAdDate);

        document.getElementById(`${pickerId}_bs_show`).textContent = bsStr;
        document.getElementById(`${pickerId}_ad_show`).textContent = adStr;
        document.getElementById(`${pickerId}_bs_val`).value = bsStr;
        document.getElementById(`${pickerId}_ad_val`).value = adStr;

        // Trigger change event
        document.getElementById(`${pickerId}_bs_val`).dispatchEvent(new Event('change', { bubbles: true }));
    }

    // Generate year options (uses XML range if available)
    function generateYearOptions(start, end, selected, useXmlRange = false) {
        let html = '';
        const actualStart = useXmlRange && xmlDataLoaded ? xmlYearRange.min : start;
        const actualEnd = useXmlRange && xmlDataLoaded ? xmlYearRange.max : end;
        for (let y = actualEnd; y >= actualStart; y--) {
            html += `<option value="${y}"${y === selected ? ' selected' : ''}>${y}</option>`;
        }
        return html;
    }

    // Get first day of week for BS month
    function getBsFirstDayOfWeek(year, month) {
        let totalDays = 0;

        for (let y = refBsYear; y < year; y++) {
            if (bsMonthDays[y]) totalDays += bsMonthDays[y].reduce((a, b) => a + b, 0);
        }
        for (let y = year; y < refBsYear; y++) {
            if (bsMonthDays[y]) totalDays -= bsMonthDays[y].reduce((a, b) => a + b, 0);
        }

        const monthDays = bsMonthDays[year] || bsMonthDays[2081];
        for (let m = 1; m < month; m++) totalDays += monthDays[m - 1];
        for (let m = 1; m < refBsMonth; m++) totalDays -= bsMonthDays[refBsYear][m - 1];

        totalDays += 1 - refBsDay;

        let dow = (refDayOfWeek + totalDays) % 7;
        if (dow < 0) dow += 7;
        return dow;
    }

    // Initial render
    renderCalendar();
}

// BS to AD conversion - uses XML mappings if available
function bsToAd(bsYear, bsMonth, bsDay) {
    // Try XML mapping first
    const bsKey = formatBsDate(bsYear, bsMonth, bsDay);
    if (bsToAdMappings[bsKey]) {
        const parts = bsToAdMappings[bsKey].split('-');
        return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    }

    // Fallback to calculation
    let totalDays = 0;

    for (let y = refBsYear; y < bsYear; y++) {
        if (bsMonthDays[y]) totalDays += bsMonthDays[y].reduce((a, b) => a + b, 0);
        else totalDays += 365;
    }
    for (let y = bsYear; y < refBsYear; y++) {
        if (bsMonthDays[y]) totalDays -= bsMonthDays[y].reduce((a, b) => a + b, 0);
        else totalDays -= 365;
    }

    const monthDays = bsMonthDays[bsYear] || bsMonthDays[2081];
    for (let m = 1; m < bsMonth; m++) totalDays += monthDays[m - 1];
    for (let m = 1; m < refBsMonth; m++) totalDays -= bsMonthDays[refBsYear][m - 1];

    totalDays += bsDay - refBsDay;

    const adDate = new Date(refAdDate);
    adDate.setDate(adDate.getDate() + totalDays);
    return adDate;
}

// AD to BS conversion - uses XML mappings if available
function adToBs(adDate) {
    // Try XML mapping first (using reverse mapping index)
    const adKey = formatAdDate(adDate);
    if (adToBsMappings[adKey]) {
        const parts = adToBsMappings[adKey].split('-');
        return {
            year: parseInt(parts[0]),
            month: parseInt(parts[1]),
            day: parseInt(parts[2])
        };
    }

    // Fallback to calculation
    const diffTime = adDate - refAdDate;
    let diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

    let bsYear = refBsYear;
    let bsMonth = refBsMonth;
    let bsDay = refBsDay + diffDays;

    while (bsDay > (bsMonthDays[bsYear] ? bsMonthDays[bsYear][bsMonth - 1] : 30)) {
        bsDay -= bsMonthDays[bsYear] ? bsMonthDays[bsYear][bsMonth - 1] : 30;
        bsMonth++;
        if (bsMonth > 12) { bsMonth = 1; bsYear++; }
    }
    while (bsDay < 1) {
        bsMonth--;
        if (bsMonth < 1) { bsMonth = 12; bsYear--; }
        bsDay += bsMonthDays[bsYear] ? bsMonthDays[bsYear][bsMonth - 1] : 30;
    }

    return { year: bsYear, month: bsMonth, day: bsDay };
}

// Format functions
function formatBsDate(year, month, day) {
    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function formatAdDate(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
