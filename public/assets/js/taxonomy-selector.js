document.addEventListener("DOMContentLoaded", () => {
    const select = id => document.getElementById(id);
    const value = id => {
        const field = select(id) || document.querySelector(`[name="${id}"]`);
        return field ? field.value : "";
    };
    const data = id => {
        const node = document.getElementById(id);
        if (!node) return [];
        try {
            const parsed = JSON.parse(node.textContent);
            return Array.isArray(parsed) ? parsed : [];
        } catch (_) {
            return [];
        }
    };

    const board = select("board");
    const subject = select("subject");
    const domain = select("domain");
    const topic = select("topic");
    const concept = select("concept");
    const boardSubjects = data("taxonomy-board-subjects");
    const subjects = data("taxonomy-subjects");
    const domains = data("taxonomy-domains");
    const topics = data("taxonomy-topics");
    const concepts = data("taxonomy-concepts");

    const populate = (element, items) => {
        if (!element) return;
        const selected = element.dataset.selected || element.value || "";
        element.innerHTML = '<option value="">-- Select --</option>';
        items.forEach(item => {
            const option = document.createElement("option");
            option.value = item.id || "";
            option.textContent = item.name || item.id || "";
            option.selected = option.value === selected;
            element.appendChild(option);
        });
    };

    const refreshSubjects = () => {
        if (!subject) return;
        const allowed = new Set(
            boardSubjects
                .filter(relation => (relation.board_id || "") === value("board"))
                .map(relation => relation.subject_id || "")
        );
        populate(subject, value("board") ? subjects.filter(item => allowed.has(item.id)) : subjects);
        if (domain) populate(domain, []);
        if (topic) populate(topic, []);
        if (concept) populate(concept, []);
    };

    const refreshDomains = () => {
        if (!domain) return;
        populate(domain, domains.filter(item => (item.subject_id || "") === value("subject")));
        if (topic) populate(topic, []);
        if (concept) populate(concept, []);
    };

    const refreshTopics = () => {
        if (!topic) return;
        populate(topic, topics.filter(item => (item.domain_id || "") === value("domain")));
        if (concept) populate(concept, []);
    };

    const refreshConcepts = () => {
        if (!concept) return;
        populate(concept, concepts.filter(item => (item.topic_id || "") === value("topic")));
    };

    board?.addEventListener("change", refreshSubjects);
    subject?.addEventListener("change", refreshDomains);
    domain?.addEventListener("change", refreshTopics);
    topic?.addEventListener("change", refreshConcepts);
});
