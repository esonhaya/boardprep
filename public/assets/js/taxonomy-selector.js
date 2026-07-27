document.addEventListener("DOMContentLoaded", () => {

    const subject = document.getElementById("subject");
    const domain = document.getElementById("domain");
    const topic = document.getElementById("topic");
    const concept = document.getElementById("concept");

    if (!subject || !domain || !topic || !concept) {
        return;
    }

    const domains = JSON.parse(
        document.getElementById("taxonomy-domains").textContent
    );

    const topics = JSON.parse(
        document.getElementById("taxonomy-topics").textContent
    );

    const concepts = JSON.parse(
        document.getElementById("taxonomy-concepts").textContent
    );

    function populate(select, items, valueKey = "id", labelKey = "name") {

        const current = select.dataset.selected || "";

        select.innerHTML = "";

        const empty = document.createElement("option");
        empty.value = "";
        empty.textContent = "-- Select --";
        select.appendChild(empty);

        items.forEach(item => {

            const option = document.createElement("option");

            option.value = item[valueKey];
            option.textContent = item[labelKey];

            if (option.value === current) {
                option.selected = true;
            }

            select.appendChild(option);

        });

    }

    function refreshDomains() {

        const filtered = domains.filter(

            d => d.subject === subject.value

        );

        domain.dataset.selected = "";
        topic.dataset.selected = "";
        concept.dataset.selected = "";

        populate(domain, filtered);

        topic.innerHTML = "";
        concept.innerHTML = "";

    }

    function refreshTopics() {

        const filtered = topics.filter(

            t => t.domain === domain.value

        );

        topic.dataset.selected = "";
        concept.dataset.selected = "";

        populate(topic, filtered);

        concept.innerHTML = "";

    }

    function refreshConcepts() {

        const filtered = concepts.filter(

            c => c.topic === topic.value

        );

        concept.dataset.selected = "";

        populate(concept, filtered);

    }

    subject.addEventListener(
        "change",
        refreshDomains
    );

    domain.addEventListener(
        "change",
        refreshTopics
    );

    topic.addEventListener(
        "change",
        refreshConcepts
    );

});
