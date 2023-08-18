<!-- BEGIN: MAIN -->
<ul class="list-unstyled">
<!-- BEGIN: PAGE_ROW -->
	<li>
		<a href={PAGE_ROW_URL} class="d-block">{PAGE_ROW_NUM}. {PAGE_ROW_SHORTTITLE}</a>
		<p class="small mb-0">{PAGE_ROW_DATE}</p>
	</li>
<!-- END: PAGE_ROW -->
</ul>

<!-- IF {PAGE_TOP_PAGINATION} -->
<nav class="mt-1" aria-label="Pagelist Pagination">
	<ul class="pagination pagination-sm justify-content-left mb-0">
		{PAGE_TOP_PAGEPREV}{PAGE_TOP_PAGINATION}{PAGE_TOP_PAGENEXT}
	</ul>
</nav>
<!-- ENDIF -->
<!-- END: MAIN -->
