# Framework Cleanup Checklist

## Architecture

- [ ] Repository only reads/writes data.
- [ ] Service contains business logic only.
- [ ] Controller only coordinates requests.
- [ ] View contains no business logic.

---

## Naming

- [ ] Consistent class names.
- [ ] Consistent file names.
- [ ] No duplicate services.
- [ ] No duplicate controllers.

---

## Session

- [ ] Use SessionService everywhere.
- [ ] No direct $_SESSION access outside SessionService.

---

## Storage

- [ ] All JSON access goes through repositories.
- [ ] No file_get_contents() in controllers or views.

---

## Views

- [ ] No calculations inside views.
- [ ] No duplicate HTML.
- [ ] Navigation is consistent.

---

## Dead Code

- [ ] Remove unused files.
- [ ] Remove unused methods.
- [ ] Remove commented code.

---

## Testing

- [ ] Quiz Flow
- [ ] Dashboard
- [ ] Learning Profile
- [ ] Attempt Saving
- [ ] Weakness Analysis
