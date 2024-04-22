// Create a new div element for the tooltip
var tooltipDiv = document.createElement("div");
tooltipDiv.style.display = "none";
tooltipDiv.classList.add('absolute');
tooltipDiv.classList.add('bg-yellow-200');
tooltipDiv.classList.add('border');
tooltipDiv.classList.add('border-yellow-400');
tooltipDiv.classList.add('text-yellow-900');
tooltipDiv.classList.add('select-none');
tooltipDiv.classList.add('p-1');
tooltipDiv.classList.add('rounded-sm');
tooltipDiv.classList.add('shadow-md');
tooltipDiv.classList.add('text-xs');
tooltipDiv.classList.add('z-10');
tooltipDiv.classList.add('max-w-48');
document.body.appendChild(tooltipDiv);

// Add event listeners to the body
document.body.addEventListener('mouseover', showTooltip);
document.body.addEventListener('mouseout', hideTooltip);
document.body.addEventListener('touchstart', showTooltip);
document.body.addEventListener('touchend', hideTooltip);

function showTooltip(event) {
    // Check if the event target has a title attribute
    if (event.target.hasAttribute('data-tooltip')) {
        // Show the tooltip and position it
        tooltipDiv.style.display = "block";
        tooltipDiv.style.left = ((event.pageX || event.touches[0].pageX) + 5) + 'px';
        tooltipDiv.style.top = ((event.pageY || event.touches[0].pageY) - 5) + 'px';
        tooltipDiv.textContent = event.target.getAttribute('data-tooltip');
    }
}

function hideTooltip(event) {
    // Check if the event target has a title attribute
    if (event.target !== tooltipDiv && !event.target.hasAttribute('data-tooltip')) {
        tooltipDiv.style.display = "none";
    }
}
