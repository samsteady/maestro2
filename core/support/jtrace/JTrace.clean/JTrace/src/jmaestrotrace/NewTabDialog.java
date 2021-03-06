package jmaestrotrace;

import java.awt.BorderLayout;
import java.awt.Dimension;
import java.awt.Frame;
import java.awt.Toolkit;
import java.awt.event.ActionEvent;
import java.awt.event.ActionListener;
import java.io.File;
import java.util.ArrayList;

import javax.swing.BoxLayout;
import javax.swing.JButton;
import javax.swing.JDialog;
import javax.swing.JLabel;
import javax.swing.JPanel;
import javax.swing.JTextField;

public class NewTabDialog extends JDialog {

	private Dimension dialogDimension = new Dimension(300, 110);
	private JTextField text;
	private Frame frame;

	public NewTabDialog(Frame aFrame) {
		super(aFrame, true);
		frame = aFrame;
		setTitle("Tab Name");
		Dimension screen = Toolkit.getDefaultToolkit().getScreenSize();
		int x = (screen.width / 2) - (dialogDimension.width / 2);
		int y = (screen.height / 2) - (dialogDimension.height / 2);
		setBounds(x, y, dialogDimension.width, dialogDimension.height);
		setDefaultCloseOperation(HIDE_ON_CLOSE);

		JPanel panel = new JPanel();
		panel.setLayout(new BoxLayout(panel, BoxLayout.PAGE_AXIS));
		text = new JTextField();
		JLabel label = new JLabel("Nome");
		JButton btnOk = new JButton("Ok");
		btnOk.addActionListener(new ActionListener() {
			public void actionPerformed(ActionEvent evt) {
				btnOkListener(evt);
			}
		});
		panel.add(label, BorderLayout.NORTH);
		panel.add(text, BorderLayout.EAST);
		panel.add(btnOk, BorderLayout.SOUTH);
		add(panel);
	}

	private void btnOkListener(ActionEvent evt) {
		Preferences p = new Preferences();
		p.newCustom(text.getText());
		close();				
		Preferences.restart();	
	}

	private void close() {
		setVisible(false);
	}
}
