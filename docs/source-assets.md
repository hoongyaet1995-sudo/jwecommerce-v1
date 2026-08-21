# Source asset register

The supplied storefront export has one bundled source asset and remote image URLs in
its source asset and mock catalog files.

| Local project path | Source |
| --- | --- |
| public/images/source/jwstudio_logo_1786766390670.jpg | Supplied export logo asset |
| public/images/catalog/*.jpg | Exact image URLs referenced by the supplied export catalog |

The catalog files are downloaded into the project so storefront rendering does not
depend on the image CDN at runtime. The URL identifiers are preserved in
DatabaseSeeder::localCatalogImage() for provenance. Before public redistribution,
verify the provider terms and original creator attribution requirements.
