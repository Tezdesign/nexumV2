# Objective
Update the "Confirm Delete" button to use an outline style instead of a solid color, remove its rounded pill shape, and ensure the embedded number tracking badge remains visible during normal and hover states to avoid Bootstrap contrast issues.

# Context & Root Cause
- The user requested to change the `btn-danger` and `rounded-pill` classes on the "Confirm Delete" button to an outline button (`btn-outline-danger`).
- **Bootstrap Contrast Issue:** When you use an outline button in Bootstrap, the default background is transparent/white, and the text is colored. If the badge inside is `bg-white text-danger` (like it currently is), it will become invisible against the white button background. 
- Conversely, if we change the badge to `bg-danger text-white`, it will look great initially, but when the user hovers over the button (which turns the whole button red), the red badge will blend into the red button and disappear!
- **Solution:** We will invert the badge colors. Default state will be a red badge (`bg-danger text-white`) inside the white outline button. We will then add a tiny CSS rule to `FA.css` so that when the button is hovered, the badge instantly flips to white (`bg-white text-danger`), maintaining perfect contrast!

# Implementation Steps

## 1. HTML Update (`_tab_transactions.html.twig`)
- Locate the `<button id="confirmDeleteBtn">`.
- Change its classes from `btn btn-danger btn-sm rounded-pill` to `btn btn-outline-danger btn-sm`.
- Change the badge classes from `badge bg-white text-danger ms-1` to `badge bg-danger text-white ms-1`.

## 2. CSS Hover Fix (`FA.css`)
- Open `public/css/FA.css`.
- Add a new rule specifically targeting the badge when the button is hovered:
  ```css
  #confirmDeleteBtn:hover #deleteCountBadge {
      background-color: #fff !important;
      color: #dc3545 !important;
  }
  ```

# Verification
When the user triggers multi-delete, the "Confirm Delete" button will expand as a square-cornered, red-outlined button. The "0" tracker inside will have a solid red background. When the user hovers their mouse over the button, the entire button will turn solid red, and the badge will perfectly invert to white, ensuring the number tracker is always legible.