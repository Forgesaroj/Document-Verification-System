/**
 * Nepali Date Converter JavaScript
 * Auto-detects BS/AD and converts
 */

const bsMonthDays = {
    2070: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2071: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2072: [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2073: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2074: [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2075: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2076: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2077: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2078: [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2079: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2080: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2081: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2082: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2083: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2084: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2085: [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2086: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2087: [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2088: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2089: [30, 32, 31, 32, 31, 31, 29, 30, 29, 30, 29, 31],
    2090: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30]
};

// Reference: BS 2070/01/01 = AD 2013/04/14
const refBsYear = 2070, refBsMonth = 1, refBsDay = 1;
const refAdDate = new Date(2013, 3, 14); // April 14, 2013

function detectDateType(dateStr) {
    const parts = dateStr.replace(/\//g, '-').split('-');
    if (parts.length !== 3) return null;

    const year = parseInt(parts[0]);
    if (isNaN(year)) return null;

    // BS years are typically 2000-2090
    // AD years for same range would be 1943-2033
    if (year >= 2000 && year <= 2090) {
        return 'BS';
    } else if (year >= 1943 && year <= 2050) {
        return 'AD';
    }
    return null;
}

function bsToAd(bsYear, bsMonth, bsDay) {
    if (!bsMonthDays[bsYear]) return null;

    let totalDays = 0;

    // Add days for complete years from reference
    for (let y = refBsYear; y < bsYear; y++) {
        if (bsMonthDays[y]) {
            totalDays += bsMonthDays[y].reduce((a, b) => a + b, 0);
        }
    }

    // Add days for complete months
    if (bsMonthDays[bsYear]) {
        for (let m = 0; m < bsMonth - 1; m++) {
            totalDays += bsMonthDays[bsYear][m];
        }
    }

    // Add remaining days
    totalDays += bsDay - 1;

    // Add to reference AD date
    const adDate = new Date(refAdDate);
    adDate.setDate(adDate.getDate() + totalDays);

    return {
        year: adDate.getFullYear(),
        month: adDate.getMonth() + 1,
        day: adDate.getDate()
    };
}

function adToBs(adYear, adMonth, adDay) {
    const inputDate = new Date(adYear, adMonth - 1, adDay);
    const diffTime = inputDate - refAdDate;
    let totalDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

    if (totalDays < 0) return null;

    let bsYear = refBsYear;
    let bsMonth = 1;
    let bsDay = 1;

    while (totalDays > 0) {
        if (!bsMonthDays[bsYear]) return null;

        const daysInMonth = bsMonthDays[bsYear][bsMonth - 1];

        if (bsDay + totalDays <= daysInMonth) {
            bsDay += totalDays;
            totalDays = 0;
        } else {
            totalDays -= (daysInMonth - bsDay + 1);
            bsMonth++;
            bsDay = 1;

            if (bsMonth > 12) {
                bsMonth = 1;
                bsYear++;
            }
        }
    }

    return { year: bsYear, month: bsMonth, day: bsDay };
}

function formatDate(y, m, d) {
    return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

function convertDate(dateStr) {
    const parts = dateStr.replace(/\//g, '-').split('-');
    if (parts.length !== 3) return { bs: '', ad: '', type: null };

    const year = parseInt(parts[0]);
    const month = parseInt(parts[1]);
    const day = parseInt(parts[2]);

    if (isNaN(year) || isNaN(month) || isNaN(day)) return { bs: '', ad: '', type: null };

    const type = detectDateType(dateStr);

    if (type === 'BS') {
        const adResult = bsToAd(year, month, day);
        if (adResult) {
            return {
                bs: formatDate(year, month, day),
                ad: formatDate(adResult.year, adResult.month, adResult.day),
                type: 'BS'
            };
        }
    } else if (type === 'AD') {
        const bsResult = adToBs(year, month, day);
        if (bsResult) {
            return {
                bs: formatDate(bsResult.year, bsResult.month, bsResult.day),
                ad: formatDate(year, month, day),
                type: 'AD'
            };
        }
    }

    return { bs: '', ad: '', type: null };
}

function initDateConverter(inputId, bsDisplayId, adDisplayId, bsHiddenId, adHiddenId) {
    const input = document.getElementById(inputId);
    const bsDisplay = document.getElementById(bsDisplayId);
    const adDisplay = document.getElementById(adDisplayId);
    const bsHidden = document.getElementById(bsHiddenId);
    const adHidden = document.getElementById(adHiddenId);

    if (!input) return;

    input.addEventListener('input', function() {
        const value = this.value.trim();
        if (value.length >= 10) {
            const result = convertDate(value);
            if (result.type) {
                if (bsDisplay) bsDisplay.textContent = result.bs + ' (BS)';
                if (adDisplay) adDisplay.textContent = result.ad + ' (AD)';
                if (bsHidden) bsHidden.value = result.bs;
                if (adHidden) adHidden.value = result.ad;
                input.classList.remove('border-red-500');
                input.classList.add('border-green-500');
            } else {
                if (bsDisplay) bsDisplay.textContent = 'Invalid date';
                if (adDisplay) adDisplay.textContent = '';
                input.classList.remove('border-green-500');
                input.classList.add('border-red-500');
            }
        } else {
            if (bsDisplay) bsDisplay.textContent = '';
            if (adDisplay) adDisplay.textContent = '';
            input.classList.remove('border-green-500', 'border-red-500');
        }
    });
}
