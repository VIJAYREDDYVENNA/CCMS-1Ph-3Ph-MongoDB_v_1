
<!-- <div class="dropdown position-fixed bottom-0 end-0 mb-3 me-3 bd-mode-toggle">
	<button class="btn btn-primary py-2 dropdown-toggle d-flex align-items-center" id="bd-theme" type="button" aria-expanded="false" data-bs-toggle="dropdown" aria-label="Toggle theme (auto)">
		<svg class="bi my-1 theme-icon-active" width="1em" height="1em"><use href="#circle-half"></use></svg>
		<span class="visually-hidden" id="bd-theme-text">Toggle theme</span>
	</button>
	<ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="bd-theme-text">
		<li>
			<button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="light" aria-pressed="false">
				<svg class="bi me-2 opacity-50" width="1em" height="1em"><use href="#sun-fill"></use></svg>
				Light
				<svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
			</button>
		</li>
		<li>
			<button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="dark" aria-pressed="false">
				<svg class="bi me-2 opacity-50" width="1em" height="1em"><use href="#moon-stars-fill"></use></svg>
				Dark
				<svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
			</button>
		</li>
		<li>
			<button type="button" class="dropdown-item d-flex align-items-center active" data-bs-theme-value="auto" aria-pressed="true">
				<svg class="bi me-2 opacity-50" width="1em" height="1em"><use href="#circle-half"></use></svg>
				Auto
				<svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
			</button>
		</li>
	</ul>
</div> -->

<style>
	.dropdown-menu {
    position: absolute;  /* Use absolute positioning */
    top: 40px;           /* Adjust top position */
    left: 0;             /* Adjust left position */
    right: 50px !important;         /* Optional, override the default right position */
    width: 100px !important;        /* Adjust width if needed */
}

@media (max-width:424px){
.custopm-position {
    position: fixed !important;
    top: auto !important;
    left: auto !important;
    right: 0px !important;
    bottom: 0px !important;
}

.dropdown-menu {
    position: absolute;  /* Use absolute positioning */
    top: -120px !important;    /* Move it up by 40px (negative direction) */
    left: -100px !important;   /* Move it left by 50px (negative direction) */
    right: auto !important;   /* Remove the default right position */
    width: 100px !important;  /* Adjust width if needed */
}

.custom-bg-color{
	background-color:#2c82c3;
	color:white !important;
}

}

</style>

<div class="me-2 custopm-position">
	<button class="btn btn-outline-primary  dropdown-toggle d-flex align-items-center border-0 custom-bg-color" id="bd-theme" type="button" aria-expanded="false" data-bs-toggle="dropdown" aria-label="Toggle theme (auto)">
		<svg class="bi my-1 theme-icon-active" width="1em" height="1em"><use href="#circle-half"></use></svg>
		<span class="visually-hidden" id="bd-theme-text">Toggle theme</span>
	</button>
	<ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="bd-theme-text">
		<li>
			<button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="light" aria-pressed="false">
				<svg class="bi me-2 opacity-50" width="1em" height="1em"><use href="#sun-fill"></use></svg>
				Light
				<svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
			</button>
		</li>
		<li>
			<button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="dark" aria-pressed="false">
				<svg class="bi me-2 opacity-50" width="1em" height="1em"><use href="#moon-stars-fill"></use></svg>
				Dark
				<svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
			</button>
		</li>
		<li>
			<button type="button" class="dropdown-item d-flex align-items-center active" data-bs-theme-value="auto" aria-pressed="true">
				<svg class="bi me-2 opacity-50" width="1em" height="1em"><use href="#circle-half"></use></svg>
				Auto
				<svg class="bi ms-auto d-none" width="1em" height="1em"><use href="#check2"></use></svg>
			</button>
		</li>
	</ul>
</div>