<div
style="
display:flex;
flex-direction:column;
gap:12px;
margin-top:20px;
"
>

<button
type="submit"
>

<?= $isEdit
    ? "💾 Update Question"
    : "💾 Save Question" ?>

</button>

<button
type="submit"
name="action"
value="save_next"
>

⏭️ Save & Next

</button>

<button
type="submit"
name="action"
value="save_similar"
>

📄 Save Similar

</button>

<a
href="/question-editor"
style="
display:block;
text-align:center;
padding:10px;
border:1px solid #ccc;
text-decoration:none;
border-radius:4px;
"
>

❌ Cancel

</a>

</div>
