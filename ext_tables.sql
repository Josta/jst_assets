CREATE TABLE pages (
	jst_assets_class 	varchar(255) DEFAULT '' NOT NULL,
	jst_assets_navclass	varchar(255) DEFAULT '' NOT NULL,
	jst_assets_style	text NOT NULL,
	jst_assets_script	text NOT NULL
);

CREATE TABLE tt_content (
	jst_assets_class	varchar(255) DEFAULT '' NOT NULL,
	jst_assets_style	text NOT NULL,
	jst_assets_script	text NOT NULL
);
