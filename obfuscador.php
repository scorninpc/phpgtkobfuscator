<?php

require_once(dirname(__FILE__) . "/external/phptrasher.class.php"); 
require_once(dirname(__FILE__) . "/external/Compiler.class.php");

$vbox = new GtkVBox();

// Cria o toolbar
$toolbar = new GtkToolBar();
$vbox->pack_start($toolbar, FALSE, FALSE);

$button = GtkToolButton::new_from_stock(Gtk::STOCK_OPEN);
$button->connect("clicked", "load_dir");
$toolbar->insert($button, -1);

$button = GtkToolButton::new_from_stock(Gtk::STOCK_EXECUTE);
$button->connect("clicked", "convert");
$toolbar->insert($button, -1);

// Carrega o diretório selecionado
function load_dir() {
	global $model;
	
	// Seleciona o diretório
	$response = array(Gtk::STOCK_OK, Gtk::RESPONSE_OK);
	$dialog = new GtkFileChooserDialog("Selecione o diretório", NULL, Gtk::FILE_CHOOSER_ACTION_SELECT_FOLDER, $response, NULL);
	$dialog->show_all();
	if ($dialog->run() == Gtk::RESPONSE_OK) {
		$path = $dialog->get_filename(); 
		
		// Processa o diretório
		$model->clear();
		
		load($path);
	}
	$dialog->destroy();
}

// Carrega os diretórios recursivamente
function load($path, $parent=NULL) {
	global $model;
	
	$dirname = substr($path, strrpos($path, "/") + 1);
	$root = $model->append($parent, array($dirname, $path, FALSE));
	
	$dir = scandir($path);
	foreach($dir as $item) {
		if(($item == ".") || ($item == "..") || ($item == ".svn")) {
			continue;
		}
		
		if(is_dir($path . "/" . $item)) {
			load($path . "/" . $item, $root);
		}
		elseif(substr($item, strpos(strtolower($item), ".")) == ".php") {
			$model->append($root, array($item, $path . "/" . $item, FALSE));
		}
	}
	
	return $root;
}

// Faz a obfuscação dos arquivos marcados
function convert() {
	global $model, $filelist;
	
	$filelist = array();
	process($model->iter_children(NULL));
	
	
	// Converte
	foreach($filelist as $file) {
		// Obfusca			
		$phptrasher = new phptrasher();
		$phptrasher->initialize();
		$phptrasher->removecomments = true; 
		$phptrasher->removelinebreaks = true; 
		$phptrasher->obfuscateclass = false; 
		$phptrasher->obfuscatefunction = true; 
		$phptrasher->obfuscatevariable = true; 
		$obfuscated = $phptrasher->trash($file); 
		file_put_contents($file, $obfuscated);


		// "Compila"
		$Compiler = new Compiler(); 
		$Compiler->OpenPHPTag = "<?php ";
		$Compiler->ClosePHPTag = "";
		$Output = $Compiler->Compile($file, $file);
	}
}

// Processa os checkeds recursivamente
function process($iter) {
	global $model, $filelist;
	
	global $model;
	
	$fullpath = $model->get_value($iter, 1);
	$file = $model->get_value($iter, 0);
	$checked = $model->get_value($iter, 2);	
	if ($checked) {
		if(substr($file, strpos(strtolower($file), ".")) == ".php") {
			$filelist[] = $fullpath;
		}
	}
	
	if($model->iter_has_child($iter)) {
		$iter = $model->iter_children($iter);
		do {
			process($iter);
		} while($iter = $model->iter_next($iter));
	}
}

// Cria o treeview
$scrolled_win = new GtkScrolledWindow();
$scrolled_win->set_policy(Gtk::POLICY_AUTOMATIC, Gtk::POLICY_AUTOMATIC);
$vbox->pack_start($scrolled_win);
    
$model = new GtkTreeStore(GObject::TYPE_STRING, GObject::TYPE_STRING, GObject::TYPE_BOOLEAN);
$view = new GtkTreeView($model);

	$column = new GtkTreeViewColumn();
	
	$cell_renderer = new GtkCellRendererPixbuf();
	$column->pack_start($cell_renderer, false);
	$cell_renderer->set_property('pixbuf-expander-open', GdkPixbuf::new_from_file(dirname(__FILE__) . "/resources/images/folder_open.png"));
	$cell_renderer->set_property('pixbuf-expander-closed', GdkPixbuf::new_from_file(dirname(__FILE__) . "/resources/images/folder_close.png"));
    
    $cell_renderer = new GtkCellRendererToggle();
	$cell_renderer->set_property('activatable', true);
	$column->pack_start($cell_renderer, false);
	$column->set_attributes($cell_renderer, 'active', 2);
	$cell_renderer->connect('toggled', "toggle");

	$cell_renderer = new GtkCellRendererText();
	$column->pack_start($cell_renderer, true);
	$column->set_attributes($cell_renderer, 'text', 0);
	
	$view->append_column($column);

$scrolled_win->add($view);

// Seleciona/desmarca no click do item
function toggle($renderer, $row) {
	global $model;
	
	$iter = $model->get_iter($row);
	$check_value = !$model->get_value($iter, 2);
	
	check($iter, $check_value);
}

// Processa recursivamente
function check($iter, $check_value) {
	global $model;
	
	$model->set($iter, 2, $check_value);
	
	if($model->iter_has_child($iter)) {
		$iter = $model->iter_children($iter);
		do {
			check($iter, $check_value);
		} while($iter = $model->iter_next($iter));
	}
}

// Cria a janela
$window = new GtkWindow();
$window->set_size_request(500, 700);
$window->add($vbox);

// Inicia a aplicação
$window->connect_simple("destroy", function() {
	Gtk::main_quit();
});
$window->show_all();
Gtk::main();
