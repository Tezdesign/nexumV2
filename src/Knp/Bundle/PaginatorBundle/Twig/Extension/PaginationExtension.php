<?php

namespace Knp\Bundle\PaginatorBundle\Twig\Extension;

use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PaginationExtension extends AbstractExtension
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('knp_pagination_render', [$this, 'renderPagination'], ['is_safe' => ['html']]),
        ];
    }

    public function renderPagination(PaginationInterface $pagination): string
    {
        $pageCount = $pagination->getPageCount();
        if ($pageCount <= 1) {
            return '';
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return '';
        }

        $currentPage = max(1, min($pagination->getCurrentPageNumber(), $pageCount));
        $pages = $pagination->getPagesInRange(2);
        if ($pages === []) {
            $pages = [$currentPage];
        }

        $html = '<nav aria-label="Pagination"><ul class="pagination">';
        $html .= $this->renderPageLink('Previous', max(1, $currentPage - 1), $currentPage === 1, $request->getPathInfo(), $request->query->all(), true);

        if ($pages[0] > 1) {
            $html .= $this->renderPageLink('1', 1, false, $request->getPathInfo(), $request->query->all());
            if ($pages[0] > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        foreach ($pages as $page) {
            $html .= $this->renderPageLink((string) $page, $page, $page === $currentPage, $request->getPathInfo(), $request->query->all());
        }

        if ($pages[array_key_last($pages)] < $pageCount) {
            if ($pages[array_key_last($pages)] < $pageCount - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= $this->renderPageLink((string) $pageCount, $pageCount, false, $request->getPathInfo(), $request->query->all());
        }

        $html .= $this->renderPageLink('Next', min($pageCount, $currentPage + 1), $currentPage === $pageCount, $request->getPathInfo(), $request->query->all(), true);
        $html .= '</ul></nav>';

        return $html;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function renderPageLink(string $label, int $page, bool $disabled, string $path, array $query, bool $isControl = false): string
    {
        $classes = ['page-item'];
        if ($disabled) {
            $classes[] = 'disabled';
        }

        $query['page'] = $page;
        $url = $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $linkClasses = ['page-link'];
        if ($isControl) {
            $linkClasses[] = 'text-nowrap';
        }

        $link = sprintf(
            '<a class="%s" href="%s">%s</a>',
            implode(' ', $linkClasses),
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        );

        if ($label !== 'Previous' && $label !== 'Next' && !$disabled && (int) $label === $page) {
            $classes[] = 'active';
        }

        return sprintf('<li class="%s">%s</li>', implode(' ', $classes), $link);
    }
}
