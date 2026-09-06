const DIFFICULTIES = ["easy", "medium", "hard"];

function wholeNumber(value) {
    const text = String(value).trim();
    return /^\+?\d+$/.test(text) ? Number(text) : null;
}

function largestRemainder(total, weights, keys) {
    const weightTotal = weights.reduce((sum, value) => sum + value, 0);
    const result = {};
    const remainders = [];
    let allocated = 0;

    keys.forEach((key, index) => {
        const exact = weightTotal > 0
            ? total * weights[index] / weightTotal
            : total / keys.length;
        const whole = Math.floor(exact);
        result[key] = whole;
        remainders.push({key, remainder: exact - whole, index});
        allocated += whole;
    });

    remainders.sort((left, right) =>
        right.remainder - left.remainder || left.index - right.index
    );
    for (let index = 0; index < total - allocated; index += 1) {
        result[remainders[index].key] += 1;
    }
    return result;
}

function redistribute(values, changed) {
    const others = DIFFICULTIES.filter(item => item !== changed);
    const remaining = 100 - values[changed];
    const redistributed = largestRemainder(
        remaining,
        others.map(item => values[item]),
        others
    );

    others.forEach(item => {
        values[item] = redistributed[item];
    });
    return values;
}

function renderDifficulty(values) {
    DIFFICULTIES.forEach(key => {
        document.getElementById(key).value = values[key];
        document.getElementById(key + "Input").value = values[key];
        document.getElementById(key + "Value").textContent = values[key];
        document.getElementById(key).setAttribute("aria-valuenow", values[key]);
        document.getElementById(key + "Input").setAttribute("aria-invalid", "false");
    });

    document.getElementById("total").textContent = 100;
    document.getElementById("status").textContent = "Valid allocation.";
    document.getElementById("status").className = "form-help";
    document.getElementById("submitButton").disabled = false;
}

function renderInvalid(changed, message) {
    const input = document.getElementById(changed + "Input");
    input.setAttribute("aria-invalid", "true");
    document.getElementById("status").textContent = message;
    document.getElementById("status").className = "form-error";
    document.getElementById("submitButton").disabled = true;
}

function updateDifficulty(changed, source = "slider") {
    const raw = source === "number"
        ? document.getElementById(changed + "Input").value
        : document.getElementById(changed).value;
    const requested = wholeNumber(raw);

    if (requested === null || requested < 0 || requested > 100) {
        renderInvalid(changed, "Enter a whole number from 0 to 100.");
        return;
    }

    const values = {};
    DIFFICULTIES.forEach(key => {
        values[key] = wholeNumber(document.getElementById(key).value) ?? 0;
    });
    values[changed] = requested;
    renderDifficulty(redistribute(values, changed));
}

DIFFICULTIES.forEach(id => {
    document.getElementById(id).addEventListener("input", () => updateDifficulty(id));
    document.getElementById(id + "Input").addEventListener(
        "input",
        () => updateDifficulty(id, "number")
    );
});

updateDifficulty("easy");
